<?php

declare(strict_types=1);

$pageTitle = $pageTitle ?? 'GenK Magazine';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/Assets/CSS/style.css">
    <link rel="stylesheet" href="/Assets/CSS/ReadForm.css">
</head>
<body>
<header id="main-header">
    <div class="nav-container">
        <a href="/index.php" class="logo">GenK Magazine</a>
        <button class="mobile-menu-btn" id="menu-toggle" aria-label="Mở menu">☰</button>
        <div class="nav-links" id="nav-menu">
            <a href="/index.php#mobile">Mobile</a>
            <a href="/index.php#ai">AI</a>
            <a href="/index.php#internet">Internet</a>
            <a href="/index.php#tin-ict">Tin ICT</a>
            <a href="/index.php#kham-pha">Khám Phá</a>
        </div>
    </div>
</header>
