// ExamSlot mascot: sits in the bottom-right corner of every page.
// It says "Hi!" once per browser session (first page opened), then only when it is clicked.
// Page changes do not replay anything: it just reappears in the same spot, resting.
(function () {
  if (document.getElementById('es-mascot')) return;
  var cs = document.currentScript;
  var img = cs && cs.src ? cs.src.replace(/js\/mascot\.js.*$/, 'img/') : '../../img/';

  var st = document.createElement('style');
  st.textContent =
    '#es-mascot{position:fixed;right:24px;bottom:10px;width:124px;height:136px;z-index:40;border:0;padding:0;background:none;cursor:pointer;-webkit-tap-highlight-color:transparent;font:inherit}' +
    '#es-mascot img{display:block;pointer-events:none;user-select:none;-webkit-user-drag:none}' +
    '#es-mascot .m-idle{position:absolute;left:0;bottom:0;width:119px;transition:transform .15s}' +
    '#es-mascot:hover .m-idle{transform:translateY(-3px)}' +
    '#es-mascot .m-wave{position:absolute;left:0;bottom:0;width:122px;height:135px;display:none}' +
    '#es-mascot .m-wave img{position:absolute;left:0;top:0;width:100%;height:100%}' +
    '#es-mascot .m-arm{transform-origin:82.86% 58.67%}' +
    '#es-mascot.say .m-idle{display:none}#es-mascot.say .m-wave{display:block;animation:esHop .45s ease-out 1}' +
    '#es-mascot.say .m-arm{animation:esWave .5s ease-in-out 5}' +
    '#es-mascot .m-bub{position:absolute;left:-26px;top:-6px;background:#fff;color:#2a262c;border:2px solid #2a262c;border-radius:16px;padding:3px 12px;font:700 15px/1.3 Poppins,system-ui,sans-serif;opacity:0;transform:scale(.6);transform-origin:90% 100%;transition:opacity .18s,transform .18s;pointer-events:none}' +
    '#es-mascot .m-bub:after{content:"";position:absolute;right:10px;bottom:-9px;width:10px;height:10px;background:#fff;border:solid #2a262c;border-width:0 2px 2px 0;transform:skewX(-25deg) rotate(35deg)}' +
    '#es-mascot.say .m-bub{opacity:1;transform:none}' +
    '@keyframes esWave{0%,100%{transform:rotate(-7deg)}50%{transform:rotate(14deg)}}' +
    '@keyframes esHop{0%{transform:translateY(0)}40%{transform:translateY(-8px)}100%{transform:translateY(0)}}' +
    '@media(max-width:700px){#es-mascot{right:12px;transform:scale(.8);transform-origin:100% 100%}.adm #es-mascot,.stu #es-mascot{bottom:74px}}' +
    '@media screen{.main{padding-bottom:130px}}' +
    '@media(prefers-reduced-motion:reduce){#es-mascot .m-arm,#es-mascot .m-wave{animation:none!important}}' +
    '@media print{#es-mascot{display:none!important}}';
  document.head.appendChild(st);

  var el = document.createElement('button');
  el.id = 'es-mascot';
  el.type = 'button';
  el.title = 'Say hi!';
  el.setAttribute('aria-label', 'ExamSlot mascot. Click to say hi.');
  el.innerHTML =
    '<img class="m-idle" alt="" src="' + img + 'mascot-idle.png">' +
    '<span class="m-wave"><img alt="" src="' + img + 'mascot-wave-body.png"><img class="m-arm" alt="" src="' + img + 'mascot-wave-arm.png"></span>' +
    '<span class="m-bub" aria-hidden="true">Hi!</span>';
  document.body.appendChild(el);

  // load the waving pose ahead of time so the first wave has no flicker
  ['mascot-wave-body.png', 'mascot-wave-arm.png'].forEach(function (f) { new Image().src = img + f; });

  var timer;
  function hi() {
    clearTimeout(timer);
    el.classList.remove('say');
    void el.offsetWidth;                 // restart the animation if clicked again
    el.classList.add('say');
    timer = setTimeout(function () { el.classList.remove('say'); }, 2700);
  }
  el.addEventListener('click', hi);

  var seen = false;
  try { seen = !!sessionStorage.getItem('es-mascot-hi'); } catch (e) {}
  if (!seen) {
    try { sessionStorage.setItem('es-mascot-hi', '1'); } catch (e) {}
    setTimeout(hi, 700);
  }
})();
