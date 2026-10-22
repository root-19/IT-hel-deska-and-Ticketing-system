<?php
// Shared <head>. Set $pageTitle before including.
$pageTitle = isset($pageTitle) ? $pageTitle . ' | ' . APP_NAME : APP_NAME;
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title><?= e($pageTitle) ?></title>
    <?php if (file_exists(__DIR__ . '/../assets/img/logo.png')): ?>
        <link rel="icon" type="image/png" href="assets/img/logo.png">
    <?php endif; ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Poppins', 'ui-sans-serif', 'system-ui', 'sans-serif'] },
                    colors: {
                        brand: {
                            50:  '#eef5ff',
                            100: '#d9e8ff',
                            200: '#bcd7ff',
                            300: '#8ebdff',
                            400: '#5997ff',
                            500: '#3372fd',
                            600: '#1d53f2',
                            700: '#153fdc',
                            800: '#1834b2',
                            900: '#1a318c',
                            950: '#0b1a4f',
                        },
                    },
                },
            },
        };
    </script>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
