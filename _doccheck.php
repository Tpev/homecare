<?php 
$zip = new ZipArchive(); 
$zip->open('C:/Users/Epicpp/Downloads/Untitled (1).docx'); 
$xml = $zip->getFromName('word/document.xml'); 
