package com.turning_leaf_technologies.cron.marc_manipulation;

import com.turning_leaf_technologies.cron.CronLogEntry;
import com.turning_leaf_technologies.cron.CronProcessLogEntry;
import com.turning_leaf_technologies.cron.IProcessHandler;
import org.apache.logging.log4j.Logger;
import org.ini4j.Ini;
import org.ini4j.Profile;
import org.marc4j.*;
import org.marc4j.marc.DataField;
import org.marc4j.marc.Record;
import org.marc4j.marc.Subfield;

import java.io.File;
import java.io.FileInputStream;
import java.io.FileOutputStream;
import java.sql.Connection;
import java.text.SimpleDateFormat;
import java.util.*;

@SuppressWarnings("unused")
public class MergeMarcUpdatesAndDeletes implements IProcessHandler {
	private String recordNumberTag = "";
	private String recordNumberPrefix = "";

	@Override
	public void doCronProcess(String servername, Ini configIni, Profile.Section processSettings, Connection dbConn, CronLogEntry cronEntry, Logger logger) {
		CronProcessLogEntry processLog = new CronProcessLogEntry(cronEntry, "Merge Marc Updates and Deletes", dbConn, logger);
		processLog.saveResults();

		//TODO: Load a list of indexing profiles that require merging
		//TODO: SQL to load the indexing profiles

		//TODO: Loop through the indexing profiles

		//Get a list of marc records that need to be processed
		//TODO: Read these from the indexing profile
		String exportPath = configIni.get("Reindex", "marcPath");
		String backupPath = configIni.get("Reindex", "marcBackupPath");
		String marcEncoding = configIni.get("Reindex", "marcEncoding");
		recordNumberTag = configIni.get("Reindex", "recordNumberTag");
		recordNumberPrefix = configIni.get("Reindex", "recordNumberPrefix");
		File mainFile = null;

		//TODO: Handle more than one set of updates and deletes (in order of creation date)
		File deletesFile = null;
		File updatesFile = null;

		int numUpdates = 0;
		int numDeletions = 0;
		int numAdditions = 0;

		try{
			File[] filesInExport = new File(exportPath).listFiles();
			if (filesInExport != null) {
				for (File exportFile : filesInExport) {
					//TODO: Read the pattern for updates and deletes from the indexing profile
					if (exportFile.getName().matches(".*updated.*")) {
						updatesFile = exportFile;
					}else if (exportFile.getName().matches(".*deleted.*")) {
						deletesFile = exportFile;
					}else if (exportFile.getName().endsWith("mrc") || exportFile.getName().endsWith("marc")) {
						mainFile = exportFile;
					}
				}

				if (mainFile == null){
					processLog.incErrors("Did not find file to merge into");
				}else {
					boolean errorOccurred = false;
					HashMap<String, Record> recordsToUpdate = new HashMap<>();
					//TODO: Handle multiple update files
					if (updatesFile != null) {
						try {
							FileInputStream marcFileStream = new FileInputStream(updatesFile);
							MarcReader updatesReader = new MarcPermissiveStreamReader(marcFileStream, true, true, marcEncoding);

							//Read a list of records in the updates file
							while (updatesReader.hasNext()) {
								Record curBib = updatesReader.next();
								String recordId = getRecordIdFromMarcRecord(curBib);
								recordsToUpdate.put(recordId, curBib);
							}
							marcFileStream.close();
						} catch (Exception e) {
							processLog.incErrors("Error processing updates file. ", e);
							errorOccurred = true;
						}
					}

					HashSet<String> recordsToDelete = new HashSet<>();
					//TODO: Handle multiple delete files
					if (deletesFile != null) {
						try {
							FileInputStream marcFileStream = new FileInputStream(deletesFile);
							MarcReader deletesReader = new MarcPermissiveStreamReader(marcFileStream, true, true, marcEncoding);

							while (deletesReader.hasNext()) {
								Record curBib = deletesReader.next();
								String recordId = getRecordIdFromMarcRecord(curBib);
								recordsToDelete.add(recordId);
							}

							marcFileStream.close();
						} catch (Exception e) {
							processLog.incErrors("Error processing deletes file. ", e);
							errorOccurred = true;
						}
					}

					String today = new SimpleDateFormat("yyyyMMdd").format(new Date());
					File mergedFile = new File(mainFile.getPath() + "." + today + ".merged");
					int numRecordsRead = 0;
					String lastRecordId = "";
					Record curBib;
					try {
						FileInputStream marcFileStream = new FileInputStream(mainFile);
						MarcReader mainReader = new MarcPermissiveStreamReader(marcFileStream, true, true, marcEncoding);

						FileOutputStream marcOutputStream = new FileOutputStream(mergedFile);
						MarcStreamWriter mainWriter = new MarcStreamWriter(marcOutputStream, "UTF-8", true);
						while (mainReader.hasNext()) {
							curBib = mainReader.next();
							String recordId = getRecordIdFromMarcRecord(curBib);
							numRecordsRead++;

							if (recordsToUpdate.containsKey(recordId)) {
								//Write the updated record
								mainWriter.write(recordsToUpdate.get(recordId));
								recordsToUpdate.remove(recordId);
								numUpdates++;
							} else if (!recordsToDelete.contains(recordId)) {
								//Unless the record is marked for deletion, write it
								mainWriter.write(curBib);
								numDeletions++;
							}

							lastRecordId = recordId;
						}

						//Anything left in the updates file is new and should be added
						for (Record newMarc : recordsToUpdate.values()){
							mainWriter.write(newMarc);
							numAdditions++;
						}
						mainWriter.close();
						marcFileStream.close();
					} catch (Exception e) {
						processLog.incErrors("Error processing main file. ", e);
						processLog.addNote("Read " + numRecordsRead + " last record read was " + lastRecordId);
						errorOccurred = true;
					}

					if (!new File(backupPath).exists()){
						if (!new File(backupPath).mkdirs()){
							processLog.incErrors("Could not create backup path");
							errorOccurred = true;
						}
					}
					if (updatesFile != null && !errorOccurred) {
						//Move to the backup directory
						if (!updatesFile.renameTo(new File(backupPath + "/" + updatesFile.getName()))) {
							processLog.incErrors("Unable to move updates file " + updatesFile.getAbsolutePath() + " to backup directory " + backupPath + "/" + updatesFile.getName());
							errorOccurred = true;
						}
					}

					if (deletesFile != null && !errorOccurred) {
						//Move to the backup directory
						if (!deletesFile.renameTo(new File(backupPath + "/" + deletesFile.getName()))) {
							processLog.incErrors("Unable to move deletion file to backup directory.");
							errorOccurred = true;
						}
					}

					if (!errorOccurred) {
						String mainFilePath = mainFile.getPath();
						if (!mainFile.renameTo(new File(backupPath + "/" + mainFile.getName()))) {
							String note = "Unable to move main file " + mainFile.getAbsolutePath() + " to backup directory " + backupPath + "/" + mainFile.getName();
							processLog.incErrors(note);
						} else {
							//Move the merged file to the main file
							if (!mergedFile.renameTo(new File(mainFilePath))){
								processLog.incErrors("Unable to move merged file to main file.");
							}else {
								logger.debug("Added " + numAdditions);
								logger.debug("Updated " + numUpdates);
								logger.debug("Deleted " + numDeletions);

								processLog.addNote("Added " + numAdditions);
								processLog.addNote("Updated " + numUpdates);
								processLog.addNote("Deleted " + numDeletions);
								processLog.saveResults();
							}
						}
					}
				}
			} else {
				logger.error("No files were found in " + exportPath);
			}
		} catch (Exception e) {
			processLog.incErrors("Unknown error merging records", e);
		}
		processLog.setFinished();
		processLog.saveResults();
	}

	private String getRecordIdFromMarcRecord(Record marcRecord) {
		List<DataField> recordIdField = getDataFields(marcRecord, recordNumberTag);
		//Make sure we only get one ils identifier
		for (DataField curRecordField : recordIdField) {
			Subfield subfieldA = curRecordField.getSubfield('a');
			if (subfieldA != null && (recordNumberPrefix.length() == 0 || subfieldA.getData().length() > recordNumberPrefix.length())) {
				if (curRecordField.getSubfield('a').getData().substring(0, recordNumberPrefix.length()).equals(recordNumberPrefix)) {
					return curRecordField.getSubfield('a').getData();
				}
			}
		}
		return null;
	}

	private List<DataField> getDataFields(Record marcRecord, String tag) {
		List variableFields = marcRecord.getVariableFields(tag);
		List<DataField> variableFieldsReturn = new ArrayList<>();
		for (Object variableField : variableFields){
			if (variableField instanceof DataField){
				variableFieldsReturn.add((DataField)variableField);
			}
		}
		return variableFieldsReturn;
	}
}
