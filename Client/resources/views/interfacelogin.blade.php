<!DOCTYPE html>
<html lang="en">
<head>
    <!-- تحديد ترميز الصفحة -->
    <meta charset="UTF-8">
    <!-- التوافق مع إصدارات المتصفحات القديمة -->
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <!-- إعداد العرض ليتناسب مع مختلف الأجهزة -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- عنوان الصفحة -->
    <title>login and Registration form in Html and Css</title>

    <!-- ربط ملف التنسيق الخارجي CSS -->
    <link rel="stylesheet" href="style.css">
    <!-- ربط مكتبة الأيقونات Boxicons -->
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <!-- CSS -->
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
</head>
<body>
    <!-- الحاوية الرئيسية للنموذجين -->
    <div class="wrapper">
        <!-- عناصر خلفية متحركة -->
        <span class="bg-animate"></span>
        <span class="bg-animate2"></span>

        <!-- نموذج تسجيل الدخول -->
        <div class="form-box login" id="login-form">
            <!-- عنوان النموذج -->
            <h2 class="animation" style="--i:0; --j:21;">login</h2>
            <!-- بداية النموذج -->
            <form method="POST" action="{{ route('login') }}">
            @csrf
                <!-- حقل إدخال اسم المستخدم -->
                <div class="input-box animation" style="--i:1; --j:22;">
                    <input type="text" name="email" required>
                    <label>Email</label>
                    <i class='bx bxs-user'></i>
                </div>
                <!-- حقل إدخال كلمة المرور -->
                <div class="input-box animation" style="--i:2; --j:23;">
                    <input type="password" name="password" required>
                    <label>password</label>
                    <i class='bx bxs-lock-alt'></i>
                </div>
                <!-- زر تسجيل الدخول -->
                <button type="submit" class="btn animation" style="--i:3; --j:24;">login</button>
                <!-- رابط للتبديل إلى نموذج التسجيل -->
                <div class="logreg-link animation" style="--i:4; --j:25;">
                    <p>Don’t have an account? <a href="#register" class="register-link">Sign Up</a></p>
                </div>
            </form>
        </div>

        <!-- معلومات ترحيبية بجانب نموذج تسجيل الدخول -->
        <div class="info-text login">
            <h2 class="animation" style="--i:0; --j:20;">Welcome Back!</h2>
        </div>

        <!-- نموذج التسجيل -->
        <div class="form-box register" id="register-form">
            <!-- عنوان النموذج -->
            <h2 class="animation signup-title" style="--i:17; --j:0;">Sign Up</h2>
            <!-- بداية نموذج التسجيل -->
            <form method="POST" action="{{ route('register') }}">
            @csrf
                <!-- حقل إدخال اسم المستخدم -->
                <div class="input-box animation" style="--i:18; --j:1">
                    <input type="text" name="name" required>
                    <label>Username</label>
                    <i class='bx bxs-user'></i>
                </div>
                <!-- حقل إدخال البريد الإلكتروني -->
                <div class="input-box animation" style="--i:19; --j:2">
                    <input type="text" name="email" required>
                    <label>Email</label>
                    <i class='bx bxs-envelope'></i>
                </div>
                <!-- حقل إدخال كلمة المرور -->
                <div class="input-box animation" style="--i:20; --j:3">
                    <input type="password" name="password" required>
                    <label>password</label>
                    <i class='bx bxs-lock-alt'></i>
                </div>
                <!-- حقل تأكيد كلمة المرور -->
                <div class="input-box animation" style="--i:21; --j:3">
                    <input type="password" name="password_confirmation" required>
                    <label>confirm password</label>
                    <i class='bx bxs-lock-alt'></i>
                </div>
                <!-- زر التسجيل -->
                <button type="submit" class="btn animation" style="--i:21; --j:4">Sign Up</button>
                <!-- رابط للتبديل إلى نموذج تسجيل الدخول -->
                <div class="logreg-link animation" style="--i:22; --j:5">
                <p class="login-text">Already have an account? <a href="/login">Login</a></p>
                </div>
            </form>
        </div>

        <!-- معلومات ترحيبية بجانب نموذج التسجيل -->
        <div class="info-text register">
            <h2 class="animation" style="--i:17; --j:0;">Welcome Back!</h2>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const wrapper = document.querySelector('.wrapper');
            const registerLink = document.querySelector('.register-link');
            const loginLink = document.querySelector('.login-link');

            // عند الضغط على "Sign Up"
            registerLink.addEventListener('click', (e) => {
                e.preventDefault();
                wrapper.classList.add('active');
            });

            // عند الضغط على "Login"
            loginLink.addEventListener('click', (e) => {
                e.preventDefault();
                wrapper.classList.remove('active');
            });

            // لو الرابط فيه #register
            if (window.location.hash === '#register') {
                wrapper.classList.add('active');
            }
        });

        document.getElementById('message-list').addEventListener('contextmenu', function (e) {
    const item = e.target.closest('.message-item');
    if (!item) return;
    e.preventDefault();
    currentMessage = item;
    contextMenu.style.top = `${e.pageY}px`;
    contextMenu.style.left = `${e.pageX}px`;
    contextMenu.style.display = 'block';
});

    </script>

    <!-- ربط ملف الجافا سكريبت -->
    <script src="script.js"></script>
</body>
</html>
