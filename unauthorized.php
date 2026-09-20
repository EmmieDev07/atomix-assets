<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unauthorized Access - Atomix</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .error-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            max-width: 500px;
            width: 100%;
            padding: 60px 40px;
            text-align: center;
        }

        .error-icon {
            font-size: 80px;
            color: #dc2626;
            margin-bottom: 30px;
        }

        .error-code {
            font-size: 48px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 10px;
        }

        .error-title {
            font-size: 24px;
            color: #1e293b;
            margin-bottom: 15px;
            font-weight: 600;
        }

        .error-message {
            font-size: 16px;
            color: #64748b;
            margin-bottom: 40px;
            line-height: 1.6;
        }

        .button-group {
            display: flex;
            gap: 15px;
            flex-direction: column;
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: #0b4a6f;
            color: white;
        }

        .btn-primary:hover {
            background: #084563;
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(11, 74, 111, 0.3);
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #1e293b;
        }

        .btn-secondary:hover {
            background: #cbd5e1;
            transform: translateY(-2px);
        }

        .portal-links {
            margin-top: 40px;
            padding-top: 40px;
            border-top: 1px solid #e2e8f0;
        }

        .portal-links h3 {
            color: #64748b;
            font-size: 14px;
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .portal-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .portal-btn {
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: 2px solid;
        }

        .portal-btn.admin {
            background: rgba(15, 23, 42, 0.05);
            border-color: #0f172a;
            color: #0f172a;
        }

        .portal-btn.admin:hover {
            background: #0f172a;
            color: white;
        }

        .portal-btn.teacher {
            background: rgba(11, 74, 111, 0.05);
            border-color: #0b4a6f;
            color: #0b4a6f;
        }

        .portal-btn.teacher:hover {
            background: #0b4a6f;
            color: white;
        }

        .portal-btn.student {
            background: rgba(14, 165, 233, 0.05);
            border-color: #0ea5e9;
            color: #0ea5e9;
        }

        .portal-btn.student:hover {
            background: #0ea5e9;
            color: white;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">
            <i class="fas fa-lock"></i>
        </div>
        <div class="error-code">403</div>
        <div class="error-title">Access Denied</div>
        <div class="error-message">
            You don't have permission to access this resource. 
            Your current role doesn't have access to this page.
        </div>

        <div class="button-group">
            <button class="btn btn-primary" onclick="goBack()">
                <i class="fas fa-arrow-left"></i> Go Back
            </button>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-sign-in-alt"></i> Login Portal
            </a>
        </div>

        <div class="portal-links">
            <h3>Login to Different Portal</h3>
            <div class="portal-buttons">
                <a href="admin/login.php" class="portal-btn admin">
                    <i class="fas fa-shield-halved"></i> Admin
                </a>
                <a href="teacher/login.php" class="portal-btn teacher">
                    <i class="fas fa-chalkboard"></i> Teacher
                </a>
                <a href="student/login.php" class="portal-btn student">
                    <i class="fas fa-book"></i> Student
                </a>
            </div>
        </div>
    </div>

    <script>
        function goBack() {
            if (document.referrer) {
                window.location.href = document.referrer;
            } else {
                window.location.href = 'index.php';
            }
        }
    </script>
</body>
</html>
