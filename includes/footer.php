  </div><!-- /content -->
</div><!-- /main -->

<div class="toast-container" id="toast-container"></div>
<script>
function showToast(title,msg,type='success'){const icons={success:'✅',error:'❌',warning:'⚠️'};const t=document.createElement('div');t.className='toast '+type;t.innerHTML='<div class="toast-icon">'+icons[type]+'</div><div class="toast-text"><div class="toast-title">'+title+'</div><div class="toast-msg">'+msg+'</div></div>';document.getElementById('toast-container').appendChild(t);setTimeout(()=>t.remove(),3500)}
function openModal(id){document.getElementById(id).classList.add('open')}
function closeModal(id){document.getElementById(id).classList.remove('open')}
document.addEventListener('click',e=>{if(e.target.classList.contains('modal-backdrop'))e.target.classList.remove('open')});
const _up=new URLSearchParams(location.search);
if(_up.get('success'))showToast('Success',decodeURIComponent(_up.get('success')),'success');
if(_up.get('error'))showToast('Error',decodeURIComponent(_up.get('error')),'error');
if(_up.get('warning'))showToast('Warning',decodeURIComponent(_up.get('warning')),'warning');
</script>
<?php if(!empty($extra_js)) echo $extra_js; ?>
</body>
</html>
