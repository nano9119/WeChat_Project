document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('login-form');
    const registerForm = document.getElementById('register-form');

const wrapper = document.querySelector('.wrapper');
const registerLink = document.querySelector('.register-link');
const loginLink = document.querySelector('.login-link');

registerLink.onclick = () => {
    wrapper.classList.add('active');
}

loginLink.onclick = () => {
    wrapper.classList.remove('active');
}

/*........................................*/

loginForm.addEventListener('submit', (e) => {
                                        e.preventDefault();
        alert('تسجيل الدخول بنجاح! جاري التوجيه...');
    //laravel
     window.location.href = 'wait';  });

/*........................................*/

registerForm.addEventListener('submit', (e) => {
        e.preventDefault();

        window.location.href = 'wait'; });
       });