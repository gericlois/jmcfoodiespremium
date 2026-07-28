window.addEventListener('scroll', function () {
  var nav = document.getElementById('nav');
  if (nav) nav.classList.toggle('scrolled', window.scrollY > 60);
  var btt = document.getElementById('btt');
  if (btt) btt.classList.toggle('show', window.scrollY > 300);
});

document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.pwd-toggle').forEach(function (button) {
    button.addEventListener('click', function () {
      var input = document.getElementById(button.dataset.pwdTarget);
      if (!input) return;
      var icon = button.querySelector('i');
      if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
      } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
      }
    });
  });

  document.querySelectorAll('[data-copy-target]').forEach(function (button) {
    button.addEventListener('click', function () {
      var target = document.getElementById(button.dataset.copyTarget);
      if (!target) return;
      navigator.clipboard.writeText(target.value || target.textContent.trim()).then(function () {
        var original = button.innerHTML;
        button.innerHTML = 'Copied!';
        setTimeout(function () { button.innerHTML = original; }, 1500);
      });
    });
  });
});
