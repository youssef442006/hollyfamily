<?php
/**
 * Header Template
 * الهيدر الرئيسي وتنسيقات التصميم
 */
if (!defined('PAGE_TITLE')) {
    define('PAGE_TITLE', 'نظام إدارة الكنيسة');
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(PAGE_TITLE) ?> | كنيسة العائلة المقدسة</title>
    
    <!-- Cairo Arabic Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Local Precompiled Tailwind CSS (Ultra Fast, Zero CDN Latency, Auto Cache-Busted) -->
    <?php $css_ver = @filemtime(__DIR__ . '/../assets/css/tailwind.min.css') ?: time(); ?>
    <link rel="stylesheet" href="assets/css/tailwind.min.css?v=<?= $css_ver ?>">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        html, body {
            font-family: 'Cairo', sans-serif;
            background-color: #f8fafc;
            overflow-x: hidden;
            max-width: 100%;
        }
        /* Custom scrollbars */
        ::-webkit-scrollbar {
            width: 7px;
            height: 7px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        .gold-gradient {
            background: linear-gradient(135deg, #e6d088 0%, #c9a44c 50%, #b0872b 100%);
        }
        .navy-gradient {
            background: linear-gradient(135deg, #15264a 0%, #1e3766 50%, #233f75 100%);
        }
    </style>
</head>
<body class="h-full flex flex-col text-slate-800 antialiased">
<div class="flex h-screen overflow-hidden">
