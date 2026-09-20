<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atomix Login Portal</title>
    <link rel="stylesheet" href="assets/css/teacher_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root{--blue-600:#0f6fbf;--blue-700:#0b5fa0}
        html,body{height:100%;margin:0}
        body {
            font-family: 'Segoe UI', Roboto, Arial, sans-serif;
            background: linear-gradient(180deg,#0e6fb5 0%, #0b74a6 60%);
            min-height: 100vh;
            color: #fff;
            display:flex;
            align-items:center;
            justify-content:center;
        }

        /* decorative circles */
        body:before, body:after{
            content:''; position:fixed; border-radius:50%; opacity:0.06; pointer-events:none; z-index:0;
        }
        body:before{width:520px;height:520px; left:6%; top:8%; background:#062a45}
        body:after{width:360px;height:360px; right:6%; bottom:6%; background:#083a5a}

        .portal-wrapper{z-index:1; width:100%; max-width:1200px; padding:4rem 2rem; box-sizing:border-box; text-align:center}
        .logo{width:160px;height:auto;margin:0 auto 1rem}
        .portal-title{font-size:34px;font-weight:800;margin:0 0 0.5rem}
        .portal-sub{max-width:760px;margin:0 auto 2rem;color:rgba(255,255,255,0.9)}

        .cards{display:flex;gap:2rem;justify-content:center;margin-top:2.5rem}
        .card{background:#fff;color:#0b2540;border-radius:14px;padding:2.25rem 2rem;width:360px;box-shadow:0 18px 40px rgba(2,6,23,0.12);position:relative}
        .card .icon{width:64px;height:64px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:linear-gradient(180deg,var(--blue-600),var(--blue-700));color:#fff;margin:0 auto 1rem}
        .card h3{margin:0 0 0.5rem;font-size:1.25rem}
        .card p{color:#64748b;font-size:0.95rem;margin:0 0 1.25rem}
        .card .btn{display:inline-block;padding:0.6rem 1rem;border-radius:8px;text-decoration:none;color:#fff;font-weight:700;background:linear-gradient(180deg,var(--blue-600),var(--blue-700));box-shadow:0 8px 18px rgba(11,37,72,0.12)}

        .badges{display:flex;gap:1.5rem;justify-content:center;margin-top:2.25rem;color:rgba(255,255,255,0.9);font-size:0.9rem}

        footer{margin-top:2.5rem;color:rgba(255,255,255,0.7);font-size:0.85rem}

        @media (max-width:900px){
            .cards{flex-direction:column;align-items:center}
            .card{width:100%;max-width:420px}
            .portal-title{font-size:26px}
        }

        @media (max-width:640px){
            body{
                align-items:flex-start;
                justify-content:flex-start;
                overflow-x:hidden;
            }

            .portal-wrapper{
                padding:2rem 1rem 2.5rem;
            }

            .logo{
                width:120px;
                margin-bottom:0.75rem;
            }

            .portal-title{
                font-size:22px;
                line-height:1.2;
            }

            .portal-sub{
                font-size:0.95rem;
                margin-bottom:1.25rem;
            }

            .cards{
                gap:1rem;
                margin-top:1.25rem;
            }

            .card{
                width:100%;
                max-width:none;
                padding:1.4rem 1.2rem;
                border-radius:12px;
            }

            .card .icon{
                width:52px;
                height:52px;
                margin-bottom:0.85rem;
            }

            .card h3{
                font-size:1.05rem;
            }

            .card p{
                font-size:0.9rem;
                margin-bottom:1rem;
            }

            .badges{
                flex-wrap:wrap;
                gap:0.75rem 1rem;
                margin-top:1.5rem;
                font-size:0.82rem;
            }

            footer{
                margin-top:1.5rem;
                font-size:0.78rem;
            }
        }

        @media (max-width:420px){
            .portal-wrapper{
                padding:1.5rem 0.75rem 2rem;
            }

            .portal-title{
                font-size:20px;
            }

            .card{
                padding:1.2rem 1rem;
            }
        }
        /* Animations */
        .portal-wrapper{opacity:0; transform:translateY(12px); transition:opacity 480ms ease, transform 480ms ease}
        .portal-wrapper.loaded{opacity:1; transform:translateY(0)}

        .card{opacity:0; transform:translateY(18px) scale(0.98); transition:transform 420ms cubic-bezier(.2,.9,.2,1), opacity 360ms ease, box-shadow 200ms ease}
        .card.pop-in{opacity:1; transform:translateY(0) scale(1)}

        .card:hover{ transform:translateY(-6px) scale(1.01); box-shadow:0 26px 60px rgba(2,6,23,0.14) }

        .icon{transform:translateY(-6px); transition:transform 420ms ease, box-shadow 300ms ease}
        .card.pop-in .icon{ transform:translateY(0) }

        @keyframes floaty {
            0% { transform: translateY(0) }
            50% { transform: translateY(-8px) }
            100% { transform: translateY(0) }
        }
        .icon i{ display:inline-block; animation: floaty 3.4s ease-in-out infinite; }
        /* Logo live animation */
        .logo{ display:block; margin:0 auto 1rem; width:160px; height:auto; will-change:transform,filter; filter:drop-shadow(0 8px 20px rgba(2,6,23,0.12)); transition:transform 420ms cubic-bezier(.2,.9,.2,1), filter 360ms ease }
        .logo.animate-float{ animation: logoFloat 4.6s ease-in-out infinite; }

        @keyframes logoFloat {
            0% { transform: translateY(0) rotate(-1deg) scale(1) }
            40% { transform: translateY(-8px) rotate(1deg) scale(1.03) }
            70% { transform: translateY(-4px) rotate(-0.5deg) scale(1.01) }
            100% { transform: translateY(0) rotate(0deg) scale(1) }
        }
    </style>
</head>
<body>
    <div class="portal-wrapper">
        <img src="logoatomix.png" alt="Atomix" class="logo">
        <h1 class="portal-title">Welcome to Atomix</h1>
        <p class="portal-sub">Your modern learning platform for interactive assessments and gamified education</p>

        <div class="cards">
            <div class="card">
                <div class="icon"><i class="fas fa-chalkboard-teacher fa-lg"></i></div>
                <h3>Teacher Portal</h3>
                <p>Create and manage assessments, track student progress, and deploy interactive quizzes</p>
                <a href="teacher/login.php" class="btn">Sign In as Teacher &rarr;</a>
            </div>
            <div class="card">
                <div class="icon"><i class="fas fa-user-graduate fa-lg"></i></div>
                <h3>Student Portal</h3>
                <p>Access interactive quizzes, play educational games, and track your learning progress</p>
                <a href="game/index.html" class="btn">Play Game &rarr;</a>
            </div>
            <div class="card">
                <div class="icon"><i class="fas fa-user-tie fa-lg"></i></div>
                <h3>Admin Portal</h3>
                <p>Manage teachers, students, classes, and oversee the entire platform administration</p>
                <a href="admin/login.php" class="btn">Sign In as Admin &rarr;</a>
            </div>
        </div>

        <div class="badges">
            <div><i class="fas fa-gamepad"></i> Gamified Learning</div>
            <div><i class="fas fa-chart-line"></i> Real-time Analytics</div>
            <div><i class="fas fa-shield-alt"></i> Secure Platform</div>
            <div><i class="fas fa-mobile-alt"></i> Responsive Design</div>
        </div>

        <footer>&copy; <?php echo date('Y'); ?> Atomix Learning Platform. All rights reserved.</footer>
    </div>
    <script>
        // Trigger entry animations and stagger card pop-ins
        document.addEventListener('DOMContentLoaded', function(){
            const wrapper = document.querySelector('.portal-wrapper');
            if (!wrapper) return;
            // small delay to let paint happen
            requestAnimationFrame(()=> setTimeout(()=> wrapper.classList.add('loaded'), 50));

            const cards = Array.from(document.querySelectorAll('.card'));
            cards.forEach((c, i) => {
                setTimeout(() => c.classList.add('pop-in'), 180 + i * 140);
            });

                // Logo float + mouse parallax
                const logo = document.querySelector('.logo');
                if (logo) {
                    // start float animation
                    logo.classList.add('animate-float');

                    // parallax effect on mouse move
                    let mouseX = 0, mouseY = 0, rx = 0, ry = 0;
                    const wrapperRect = wrapper.getBoundingClientRect();
                    const maxTranslate = 12; // px
                    const ease = 0.08;

                    function onMove(e) {
                        const x = e.clientX - (wrapperRect.left + wrapperRect.width / 2);
                        const y = e.clientY - (wrapperRect.top + wrapperRect.height / 2);
                        mouseX = (x / (wrapperRect.width / 2));
                        mouseY = (y / (wrapperRect.height / 2));
                    }

                    function update() {
                        // lerp
                        rx += (mouseX - rx) * ease;
                        ry += (mouseY - ry) * ease;
                        const tx = -rx * maxTranslate;
                        const ty = -ry * (maxTranslate * 0.6);
                        const r = rx * 6; // rotation deg
                        logo.style.transform = `translate3d(${tx}px, ${ty}px, 0) rotate(${r}deg)`;
                        requestAnimationFrame(update);
                    }

                    document.addEventListener('mousemove', onMove);
                    // reset when leaving
                    document.addEventListener('mouseleave', function(){ mouseX = 0; mouseY = 0; });
                    update();
                }
        });
    </script>
</body>
</html>
