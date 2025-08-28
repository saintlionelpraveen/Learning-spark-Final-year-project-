<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Logging Out</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }
        body {
            background: linear-gradient(135deg, #1e3c72, #2a5298, #3b5998, #8e44ad);
            background-size: 300% 300%;
            animation: gradientShift 20s ease infinite;
            color: #fff;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
        }
        @keyframes gradientShift {
            0% { background-position: 0% 0%; }
            50% { background-position: 100% 100%; }
            100% { background-position: 0% 0%; }
        }
        .container {
            padding: 40px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            box-shadow: 0 0 40px rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(20px);
            text-align: center;
            animation: pulseContainer 3s infinite ease-in-out;
        }
        @keyframes pulseContainer {
            0% { transform: scale(1); box-shadow: 0 0 20px rgba(255, 255, 255, 0.3); }
            50% { transform: scale(1.01); box-shadow: 0 0 40px rgba(255, 255, 255, 0.5); }
            100% { transform: scale(1); box-shadow: 0 0 20px rgba(255, 255, 255, 0.3); }
        }
        h1 {
            font-size: 2em;
            text-shadow: 0 0 15px rgba(255, 255, 255, 0.8);
            letter-spacing: 1px;
            margin-bottom: 20px;
        }
        .particles {
            position: absolute;
            width: 100%;
            height: 100%;
            pointer-events: none;
            overflow: hidden;
        }
        .particle {
            position: absolute;
            background: rgba(255, 255, 255, 0.7);
            border-radius: 50%;
            animation: float 10s infinite;
        }
        @keyframes float {
            0% { transform: translateY(100vh) scale(0.5); opacity: 0.7; }
            100% { transform: translateY(-10vh) scale(1.2); opacity: 0; }
        }
    </style>
</head>
<body>
    <div class="particles" id="particles"></div>
    <div class="container">
        <h1>Logging Out...</h1>
    </div>

    <script>
        const particlesContainer = document.getElementById('particles');
        for (let i = 0; i < 80; i++) {
            const particle = document.createElement('div');
            particle.classList.add('particle');
            particle.style.width = `${Math.random() * 5 + 2}px`;
            particle.style.height = particle.style.width;
            particle.style.left = `${Math.random() * 100}vw`;
            particle.style.animationDuration = `${Math.random() * 6 + 4}s`;
            particle.style.animationDelay = `${Math.random() * 5}s`;
            particlesContainer.appendChild(particle);
        }

        // Redirect after 2 seconds
        setTimeout(() => {
            <?php session_destroy(); ?>
            window.location.href = 'login.php';
        }, 2000);
    </script>
</body>
</html>