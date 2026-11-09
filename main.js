// ===== Публичный сайт =====
function openModal(){document.getElementById('mBook').classList.add('open')}
function closeModal(){document.getElementById('mBook').classList.remove('open')}
function openLight(el){
  document.getElementById('lightImg').src=el.querySelector('img').src;
  document.getElementById('mLight').classList.add('open');
}
function closeLight(){document.getElementById('mLight').classList.remove('open')}

// ===== Форма бронирования =====
document.getElementById('bForm').addEventListener('submit',function(e){
  e.preventDefault();
  const st=document.getElementById('fStatus');
  if(!document.getElementById('fCheck').checked){
    st.className='f-status err';
    st.textContent='Согласитесь с политикой!';
    return;
  }
  const name=document.getElementById('fName').value;
  const phone=document.getElementById('fPhone').value;
  const date=document.getElementById('fDate').value;
  let arr=JSON.parse(localStorage.getItem('bookings'))||[];
  arr.unshift({name:name,phone:phone,date:date,timestamp:new Date().toLocaleString('ru-RU')});
  localStorage.setItem('bookings',JSON.stringify(arr));
  st.className='f-status ok';
  st.textContent='✅ Забронировано! Мы свяжемся с вами.';
  this.reset();
  setTimeout(function(){ closeModal(); }, 2000);
});

// ===== Fallback для отсутствующих изображений =====
document.querySelectorAll('img').forEach(function(img){
  img.addEventListener('error', function(){
    if(this.dataset.fallback) return;
    this.dataset.fallback='1';
    this.src='data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="600" height="400"><rect width="100%" height="100%" fill="%23141a36"/><text x="50%" y="50%" fill="%238c9bff" font-family="sans-serif" font-size="24" text-anchor="middle" dy=".3em">CDC GALAXY</text></svg>';
  });
});