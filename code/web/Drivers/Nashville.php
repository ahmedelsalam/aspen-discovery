<?php

require_once ROOT_DIR . '/Drivers/CarlX.php';

class Nashville extends CarlX {

	public function __construct($accountProfile)
	{
		parent::__construct($accountProfile);
	}

	public function completeFinePayment(User $patron, UserPayment $payment)
	{
		global $logger;
		$accountLinesPaid = explode(',', $payment->finesPaid);
		$user = new User();
		$user->id = $payment->userId;
		if ($user->find(true)) {
			$patronId = $user->cat_username;
		} else {
			return ['success' => false, 'message' => 'User Payment ' . $payment->id . 'failed with Invalid Patron'];
		}
		$allPaymentsucceed = true;
		foreach ($accountLinesPaid as $line) {
			// MSB Payments are in the form of fineId|paymentAmount
			list($feeId, $pmtAmount) = explode('|', $line);
			$response = $this->feePaidViaSIP('01', '02', $pmtAmount, 'USD', $feeId, $payment->id, $patronId);
			if ($response['success'] === false) {
				$logger->log("MSB Payment CarlX update failed on Payment Reference ID $payment->id on FeeID $feeId : " . $response['message'], Logger::LOG_ERROR);
				$allPaymentsucceed = false;
			}
		}
		if ($allPaymentsucceed === false) {
			$success = false;
			$message = "MSB Payment CarlX update failed.";
			$payment->completed = 9;
			$body = "MSB Payment CarlX update failed for Payment Reference ID $payment->id . Refer to log for more detail.";
			require_once ROOT_DIR . '/sys/Email/Mailer.php';
			$mailer = new Mailer();
			$mailer->send($systemVariables->errorEmail, "$serverName Error with MSB Payment CarlX update", $body);
		} else {
			$success = true;
			$message = "MSB payment successfully recorded in CarlX.";
			$payment->completed = 1;
		}
		$payment->update();
		return ['success' => $success, 'message' => $message];
	}

	public function canPayFine($system){
		$canPayFine = false;
		if ($system == 'NPL') {
			$canPayFine = true;
		}
		return $canPayFine;
	}

	protected function feePaidViaSIP($feeType = '01', $pmtType = '02', $pmtAmount, $curType = 'USD', $feeId = '', $transId = '', $patronId = '') {
		$mySip = $this->initSIPConnection();
		if (!is_null($mySip)) {
			$in = $mySip->msgFeePaid($feeType, $pmtType, $pmtAmount, $curType, $feeId, $transId, $patronId);
			$msg_result = $mySip->get_message($in);
			if (preg_match("/^38/", $msg_result)) {
				$result = $mySip->parseFeePaidResponse($msg_result);
				$success = ($result['fixed']['PaymentAccepted'] == 'Y');
				$message = $result['variable']['AF'][0];
				if (!$success) {
					// $patron = $result['variable']['AA'][0];
					$transaction = $result['variable']['BK'][0];
					$message = empty($transaction) ? $message : $transaction . ": " . $message;
				}
			}
			return ['success' => $success, 'message' => $message];
		} else {
			return ['success' => false, 'message' => ['text' => 'sip_connect_fail', 'defaultText' => 'Could not connect to circulation server, please try again later.']];
		}
	}

	public function getFineSystem($branchId){
		if (($branchId >= 30 && $branchId <= 178 && $branchId != 42 && $branchId != 171) || ($branchId >= 180 && $branchId <= 212 && $branchId != 185 && $branchId != 187)) {
			return "MNPS";
		} else {
			return "NPL";
		}
	}

	function getSelfRegTemplate($reason){
		if ($reason == 'duplicate_email'){
			return 'Emails/nashville-self-registration-denied-duplicate_email.tpl';
		}elseif ($reason == 'duplicate_name+birthdate') {
			return 'Emails/nashville-self-registration-denied-duplicate_name+birthdate.tpl';
		}elseif ($reason == 'success') {
			return 'Emails/nashville-self-registration.tpl';
		}else{
			return;
		}
	}

	protected function initSIPConnection() {
		$mySip = new sip2();
		$mySip->hostname = $this->accountProfile->sipHost;
		$mySip->port = $this->accountProfile->sipPort;

		if ($mySip->connect()) {
			//send selfcheck status message
			$in = $mySip->msgSCStatus();
			$msg_result = $mySip->get_message($in);
			// Make sure the response is 98 as expected
			if (preg_match("/^98/", $msg_result)) {
				$result = $mySip->parseACSStatusResponse($msg_result);

				//  Use result to populate SIP2 setings
				// These settings don't seem to apply to the CarlX Sandbox. pascal 7-12-2016
				if (isset($result['variable']['AO'][0])) {
					$mySip->AO = $result['variable']['AO'][0]; /* set AO to value returned */
				} else {
					$mySip->AO = 'NASH'; /* set AO to value returned */ // hardcoded for Nashville
				}
				if (isset($result['variable']['AN'][0])) {
					$mySip->AN = $result['variable']['AN'][0]; /* set AN to value returned */
				} else {
					$mySip->AN = '';
				}
			}
			return $mySip;
		} else {
			return null;
		}
	}

}
