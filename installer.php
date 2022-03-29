<?php include 'db_connect.php' ?>
<h1>Installer</h1>
<p>Drücke auf den Knopf um alle Tabellen zu erzeugen.</p>
<p>Bitte aufpassen das in der <b>db_connect.php</b> Datei die MySQL Daten stehen</p>
<button onclick="install();"></button>
<?php
    function install() {
        $sql = file_get_contents('database/database.sql');
        $mysqli->multi_query($sql);
        print("MySQL Tables wurden eingerichet");
    }
?>