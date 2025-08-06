<?php

function backupDatabaseTables($host, $user, $password, $dbname, $outputFile) {
    // Create a connection to the MySQL database
    $conn = new mysqli($host, $user, $password, $dbname);

    // Check the connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Get all tables in the database
    $tables = array();
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }

    $sqlScript = "";

    // Loop through each table
    foreach ($tables as $table) {
        // Get the table creation script
        $result = $conn->query("SHOW CREATE TABLE $table");
        $row = $result->fetch_row();
        $sqlScript .= "\n\n" . $row[1] . ";\n\n";

        // Get the table data
        $result = $conn->query("SELECT * FROM $table");
        $columnCount = $result->field_count;

        // Loop through the rows
        for ($i = 0; $i < $columnCount; $i++) {
            while ($row = $result->fetch_row()) {
                $sqlScript .= "INSERT INTO $table VALUES(";
                for ($j = 0; $j < $columnCount; $j++) {
                    $row[$j] = $row[$j] ? "'".str_replace("\n", "\\n", addslashes($row[$j]))."'": "NULL";
                    $sqlScript .= $row[$j];
                    if ($j < ($columnCount - 1)) {
                        $sqlScript .= ', ';
                    }
                }
                $sqlScript .= ");\n";
            }
        }

        $sqlScript .= "\n";
    }

    // Save the SQL script to a file
    if (!empty($sqlScript)) {
        $backup_file = fopen($outputFile, "w");
        fwrite($backup_file, $sqlScript);
        fclose($backup_file);

        echo "Database backup successfully created in " . $outputFile;
    } else {
        echo "An error occurred while creating the backup.";
    }

    // Close the connection
    $conn->close();
}

// Usage
$host = 'localhost'; // your database host
$user = 'your_username'; // your database username
$password = 'your_password'; // your database password
$dbname = 'your_database'; // your database name
$outputFile = 'backup1.sql'; // output file name

backupDatabaseTables($host, $user, $password, $dbname, $outputFile);
