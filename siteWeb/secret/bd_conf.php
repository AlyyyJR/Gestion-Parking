<?php
$pdo = new PDO(
    'pgsql:host=localhost;port=5432;dbname=parking_bd',
    'alyyyjr',
    'alyyyjr123'
);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);