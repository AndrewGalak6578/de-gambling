/* ═══════════════════════════════════════════════════════════════
   G-SHT High Roller VIP Club - Single Page Application
   ═══════════════════════════════════════════════════════════════ */
const API='/api/v1';
let token=localStorage.getItem('token'),user=null,currentPage='login',sidebarOpen=false,currentRotation=0,adminSelectedInterventionUserId=null;

/* ── SVG Icon Library ── */
const icons={
  dashboard:`<svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>`,
  wallet:`<svg viewBox="0 0 24 24"><rect x="2" y="6" width="20" height="14" rx="2"/><path d="M2 10h20"/><circle cx="17" cy="14" r="1.5"/></svg>`,
  games:`<svg viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M8 4v16"/><path d="M16 4v16"/><path d="M2 12h20"/></svg>`,
  settings:`<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M12 1v2m0 18v2M4.22 4.22l1.42 1.42m12.72 12.72l1.42 1.42M1 12h2m18 0h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>`,
  admin:`<svg viewBox="0 0 24 24"><path d="M12 2L3 7v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-9-5z"/><path d="M9 12l2 2 4-4"/></svg>`,
  logout:`<svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>`,
  back:`<svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>`,
  check:`<svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>`,
  x:`<svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>`,
  dice:`<svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8" cy="8" r="1.25"/><circle cx="12" cy="12" r="1.25"/><circle cx="16" cy="16" r="1.25"/><circle cx="8" cy="16" r="1.25"/><circle cx="16" cy="8" r="1.25"/></svg>`,
  wheel:`<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/><line x1="12" y1="2" x2="12" y2="9"/><line x1="12" y1="15" x2="12" y2="22"/><line x1="2" y1="12" x2="9" y2="12"/><line x1="15" y1="12" x2="22" y2="12"/></svg>`,
  empty:`<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="8" y1="15" x2="16" y2="15"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>`
};
function icon(name,cls=''){return`<span class="nav-icon ${cls}">${icons[name]||''}</span>`}
function esc(value){return String(value??'').replace(/[&<>"']/g,ch=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]))}
function fmtDate(value){return value?new Date(value).toLocaleString():'Open-ended'}
function interventionLabel(type){
  return {admin_bet_block:'Betting block',admin_deposit_block:'Deposit block',admin_win_limit:'Win count limit',admin_cool_off:'Cool-off',self_exclusion:'Self-exclusion',circuit_breaker:'Circuit breaker'}[type]||type
}

/* ── API Helper ── */
async function api(path,options={}){
  const headers={'Content-Type':'application/json','Accept':'application/json'};
  if(token)headers['Authorization']=`Bearer ${token}`;
  try{
    const res=await fetch(`${API}${path}`,{...options,headers});
    const data=await res.json().catch(()=>({}));
    if(!res.ok){
      if(res.status===401&&token){token=null;localStorage.removeItem('token');navigate('login');return null}
      return{...data,_status:res.status}
    }
    return data
  }catch(e){showToast('Network error. Check connection.','error');return null}
}

/* ── Toast System ── */
function showToast(message,type='info'){
  const c=document.getElementById('toast-container');if(!c)return;
  const el=document.createElement('div');el.className=`toast toast-${type}`;
  const ic=type==='success'?icons.check:type==='error'?icons.x:'<svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>';
  el.innerHTML=`<span style="width:16px;height:16px;flex-shrink:0">${ic}</span><span>${message}</span>`;
  c.appendChild(el);
  setTimeout(()=>{el.style.opacity='0';el.style.transform='translateX(20px)';el.style.transition='all .25s ease';setTimeout(()=>el.remove(),300)},3500)
}

/* ── Custom Modal System ── */
function showModal({title,description,inputLabel,inputValue,inputPlaceholder,confirmText,confirmClass,onConfirm,showInput=false}){
  const overlay=document.createElement('div');overlay.className='modal-overlay';
  overlay.innerHTML=`<div class="modal">
    <div class="modal-title">${title}</div>
    <div class="modal-desc">${description||''}</div>
    ${showInput?`<div><label class="label">${inputLabel||'Value'}</label><input type="text" class="input" id="modal-input" value="${inputValue||''}" placeholder="${inputPlaceholder||''}"></div>`:''}
    <div class="modal-actions">
      <button class="btn btn-ghost btn-sm" id="modal-cancel">Cancel</button>
      <button class="btn ${confirmClass||'btn-gold'} btn-sm" id="modal-confirm">${confirmText||'Confirm'}</button>
    </div>
  </div>`;
  document.body.appendChild(overlay);
  const inp=overlay.querySelector('#modal-input');if(inp)setTimeout(()=>inp.focus(),50);
  overlay.querySelector('#modal-cancel').onclick=()=>overlay.remove();
  overlay.addEventListener('click',e=>{if(e.target===overlay)overlay.remove()});
  overlay.querySelector('#modal-confirm').onclick=()=>{
    const val=inp?inp.value:'';overlay.remove();onConfirm(val);
  }
}

/* ── Routing ── */
function navigate(page,params={}){window.location.hash=page;renderPage(page,params)}
function hasToken(){return!!token}

function renderPage(page,params={}){
  currentPage=page;const app=document.getElementById('app');if(!app)return;
  document.querySelectorAll('.nav-item').forEach(n=>n.classList.remove('active'));
  if(!hasToken()&&page!=='login'&&page!=='register'){page='login';window.location.hash='login'}
  switch(page){
    case'login':renderLogin(app);break;case'register':renderRegister(app);break;
    case'dashboard':renderDashboard(app);break;case'wallet':renderWallet(app);break;
    case'games':renderGames(app);break;case'game-play':renderGamePlay(app,params);break;
    case'profile':renderProfile(app);break;case'admin':renderAdmin(app);break;
    default:renderDashboard(app)
  }
  const navItem=document.querySelector(`.nav-item[data-page="${page}"]`);if(navItem)navItem.classList.add('active')
}

window.addEventListener('hashchange',()=>{
  let hash=window.location.hash.slice(1)||'dashboard';let params={};
  if(hash.startsWith('game-play/')){params={gameId:hash.split('/')[1]};hash='game-play'}
  renderPage(hash,params)
})

/* ── Auth Handlers ── */
async function handleLogin(email,password){
  if(!email||!password)return showToast('Email and password required.','error');
  const data=await api('/auth/login',{method:'POST',body:JSON.stringify({email,password})});
  if(!data||data._status)return showToast(data?.message||'Login failed.','error');
  token=data.token;localStorage.setItem('token',token);user=data.user;
  showToast('Welcome back!','success');navigate('dashboard')
}

async function handleRegister(name,email,password,passwordConfirmation){
  if(!name||!email||!password)return showToast('All fields required.','error');
  if(password!==passwordConfirmation)return showToast('Passwords do not match.','error');
  const data=await api('/auth/register',{method:'POST',body:JSON.stringify({name,email,password,password_confirmation:passwordConfirmation})});
  if(!data||data._status)return showToast(data?.message||'Registration failed.','error');
  token=data.token;localStorage.setItem('token',token);user=data.user;
  showToast('Account created!','success');navigate('dashboard')
}

async function handleLogout(){
  await api('/auth/logout',{method:'POST'});
  token=null;localStorage.removeItem('token');user=null;navigate('login')
}

/* ── Login Page ── */
function renderLogin(app){app.innerHTML=`
<div id="toast-container" class="toast-container"></div>
<div class="auth-bg">
  <div class="auth-card">
    <div class="auth-logo"><img src="/logo.png" alt="G-SHT"></div>
    <div class="auth-form">
      <div class="auth-title gold-text">Welcome Back</div>
      <div class="auth-subtitle">Sign in to your VIP account</div>
      <div class="form-group"><label class="label">Email</label><input id="login-email" type="email" class="input" placeholder="email@example.com"></div>
      <div class="form-group"><label class="label">Password</label><input id="login-password" type="password" class="input" placeholder="Enter password"></div>
      <button onclick="handleLogin(document.getElementById('login-email').value,document.getElementById('login-password').value)" class="btn btn-gold w-full py-3">Sign In</button>
      <div class="auth-divider">Don't have an account? <a href="#" onclick="navigate('register');return false;">Create one</a></div>
    </div>
  </div>
</div>`;setTimeout(()=>document.getElementById('login-email')?.focus(),100)}

/* ── Register Page ── */
function renderRegister(app){app.innerHTML=`
<div id="toast-container" class="toast-container"></div>
<div class="auth-bg">
  <div class="auth-card">
    <div class="auth-logo"><img src="/logo.png" alt="G-SHT"></div>
    <div class="auth-form">
      <div class="auth-title gold-text">Create Account</div>
      <div class="auth-subtitle">Join the VIP club</div>
      <div class="form-group"><label class="label">Name</label><input id="reg-name" type="text" class="input" placeholder="Your name"></div>
      <div class="form-group"><label class="label">Email</label><input id="reg-email" type="email" class="input" placeholder="email@example.com"></div>
      <div class="form-group"><label class="label">Password</label><input id="reg-password" type="password" class="input" placeholder="Min 8 characters"></div>
      <div class="form-group"><label class="label">Confirm Password</label><input id="reg-password-confirm" type="password" class="input" placeholder="Repeat password"></div>
      <button onclick="handleRegister(document.getElementById('reg-name').value,document.getElementById('reg-email').value,document.getElementById('reg-password').value,document.getElementById('reg-password-confirm').value)" class="btn btn-gold w-full py-3">Create Account</button>
      <div class="auth-divider">Already a member? <a href="#" onclick="navigate('login');return false;">Sign in</a></div>
    </div>
  </div>
</div>`}

/* ── App Layout Shell ── */
function renderLayout(app){app.innerHTML=`
<div id="toast-container" class="toast-container"></div>
<button class="sidebar-toggle" onclick="toggleSidebar()"><svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
<div class="mobile-overlay" id="mobile-overlay" onclick="toggleSidebar()"></div>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-header"><img src="/logo.png" alt="G-SHT"></div>
  <nav class="sidebar-nav">
    <button class="nav-item" data-page="dashboard" onclick="navigate('dashboard')">${icon('dashboard')} Dashboard</button>
    <button class="nav-item" data-page="wallet" onclick="navigate('wallet')">${icon('wallet')} Wallet</button>
    <button class="nav-item" data-page="games" onclick="navigate('games')">${icon('games')} Games</button>
    <button class="nav-item" data-page="profile" onclick="navigate('profile')">${icon('settings')} Settings</button>
    <button class="nav-item" data-page="admin" onclick="navigate('admin')" id="admin-nav" style="display:none">${icon('admin')} Admin</button>
  </nav>
  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="sidebar-avatar">${user?.name?.[0]?.toUpperCase()||'?'}</div>
      <div class="sidebar-user-info">
        <p class="sidebar-user-name">${user?.name||'User'}</p>
        <p class="sidebar-user-email">${user?.email||''}</p>
      </div>
    </div>
    <button onclick="handleLogout()" class="btn btn-ghost w-full btn-sm">${icon('logout')} Sign Out</button>
  </div>
</aside>
<main class="main-content" id="main-content"><div id="page-content"></div></main>`}

function toggleSidebar(){sidebarOpen=!sidebarOpen;document.getElementById('sidebar').classList.toggle('open',sidebarOpen);document.getElementById('mobile-overlay').classList.toggle('show',sidebarOpen)}

/* ── Dashboard ── */
async function renderDashboard(app){
  renderLayout(app);const content=document.getElementById('page-content');
  content.innerHTML='<div class="spinner"></div>';
  const[dashboardData,walletData,selfExclData]=await Promise.all([api('/user/dashboard'),api('/wallet'),api('/user/self-exclusion')]);
  user=dashboardData?.user||user;
  const adminCheck=await api('/admin/users');const isAdmin=adminCheck&&!adminCheck._status;
  document.getElementById('admin-nav').style.display=isAdmin?'flex':'none';
  const balance=walletData?.balance||'0.00';
  const riskScore=dashboardData?.dashboard?.risk_score??0;
  const selfExcluded=dashboardData?.dashboard?.self_excluded??false;
  const coolOffActive=dashboardData?.dashboard?.cool_off_active??false;
  const intervention=dashboardData?.dashboard?.active_intervention;
  const selfExcl=selfExclData?.intervention;
  const riskLevel=riskScore<30?'Low':riskScore<60?'Medium':riskScore<75?'High':'Critical';
  const riskColor=riskScore<30?'badge-green':riskScore<60?'badge-yellow':riskScore<75?'badge-red':'badge-red';

  content.innerHTML=`
  <div class="page-header"><h1 class="page-title gold-text">Dashboard</h1><p class="page-subtitle">Welcome back, ${user?.name||'Player'}</p></div>
  <div class="stats-grid">
    <div class="stat-card"><div class="stat-label">Balance</div><div class="stat-value gold-text">$${parseFloat(balance).toFixed(2)}</div><div class="stat-sub">Available funds</div></div>
    <div class="stat-card"><div class="stat-label">Risk Score</div><div class="stat-value"><span class="badge ${riskColor}">${riskScore} - ${riskLevel}</span></div><div class="stat-sub">${riskScore<75?'Within normal limits':'Cooling active'}</div></div>
    <div class="stat-card"><div class="stat-label">Status</div><div class="stat-value"><span class="badge ${user?.status==='active'?'badge-green':'badge-red'}">${user?.status||'active'}</span></div><div class="stat-sub">${selfExcluded?'Self-excluded':coolOffActive?'Cool-off active':'Active member'}</div></div>
    <div class="stat-card"><div class="stat-label">Games</div><div class="stat-value silver-text" id="game-count">--</div><div class="stat-sub"><a href="#" onclick="navigate('games');return false;" style="color:var(--gold-300);text-decoration:none;font-weight:600">Browse games &rarr;</a></div></div>
  </div>
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    ${selfExcluded?`<div class="card card-gold"><h3 class="font-bold mb-3">Self-Exclusion Active</h3><div class="space-y-3 text-sm" style="color:var(--text-secondary)"><p>Betting blocked until <strong>${new Date(selfExcl.ends_at).toLocaleDateString()}</strong>.</p>${selfExcl.payload?.reason?`<p class="p-3 rounded" style="background:var(--surface-1);border:1px solid var(--border-subtle)">Reason: ${selfExcl.payload.reason}</p>`:''}</div></div>`:
      coolOffActive?`<div class="card card-gold"><h3 class="font-bold mb-3">Cool-off Active</h3><div class="space-y-3 text-sm" style="color:var(--text-secondary)"><p>A 24-hour cooling break was triggered.</p>${intervention?.payload?.message?`<p class="p-3 rounded text-xs" style="background:var(--surface-1);border:1px solid var(--border-subtle);color:var(--text-muted)">${intervention.payload.message}</p>`:''}</div></div>`:
      `<div class="card card-gold"><h3 class="font-bold mb-2">Quick Play</h3><p class="text-sm mb-4" style="color:var(--text-secondary)">Jump straight into your favorite game.</p><div class="flex gap-3"><button onclick="navigate('game-play',{gameId:'dice'})" class="btn btn-gold btn-sm">${icon('dice')} Dice</button><button onclick="navigate('game-play',{gameId:'spin-to-win'})" class="btn btn-silver btn-sm">${icon('wheel')} Spin</button></div></div>`}
    <div class="card"><h3 class="font-bold mb-2">Wallet</h3><p class="text-sm mb-4" style="color:var(--text-secondary)">Manage deposits and withdrawals.</p><div class="flex gap-3"><button onclick="navigate('wallet')" class="btn btn-gold btn-sm">Deposit</button><button onclick="navigate('wallet')" class="btn btn-ghost btn-sm">Withdraw</button></div></div>
  </div>`;
  const gamesData=await api('/games');
  if(gamesData&&Array.isArray(gamesData))document.getElementById('game-count').textContent=gamesData.length;
  else if(gamesData?.length!==undefined)document.getElementById('game-count').textContent=gamesData.length;
}

/* ── Wallet ── */
async function renderWallet(app){
  renderLayout(app);const content=document.getElementById('page-content');
  const walletData=await api('/wallet');const balance=walletData?.balance||'0.00';
  content.innerHTML=`
  <div class="page-header"><h1 class="page-title gold-text">Wallet</h1><p class="page-subtitle">Manage your funds</p></div>
  <div class="stats-grid"><div class="stat-card"><div class="stat-label">Available Balance</div><div class="stat-value gold-text" id="wallet-balance">$${parseFloat(balance).toFixed(2)}</div><div class="stat-sub">USD</div></div></div>
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="card"><h3 class="font-bold mb-4">Deposit Funds</h3>
      <div class="space-y-4">
        <div><label class="label">Amount (USD)</label><input id="deposit-amount" type="number" class="input" value="50" min="0.01" step="0.01"></div>
        <div><label class="label">Cryptocurrency</label><select id="deposit-coin" class="select"><option value="btc">Bitcoin (BTC)</option><option value="eth">Ethereum (ETH)</option><option value="usdt">Tether (USDT)</option></select></div>
        <button onclick="handleDeposit()" class="btn btn-gold w-full" id="deposit-btn">Generate Invoice</button>
        <div id="deposit-result" class="hidden"></div>
      </div>
    </div>
    <div class="card"><h3 class="font-bold mb-4">Request Withdrawal</h3>
      <div class="space-y-4">
        <div><label class="label">Amount (USD)</label><input id="withdraw-amount" type="number" class="input" value="10" min="0.01" step="0.01"></div>
        <div><label class="label">Recipient Address</label><input id="withdraw-destination" type="text" class="input" placeholder="0x... or 1..."></div>
        <div><label class="label">Network</label><select id="withdraw-method" class="select"><option value="crypto">Crypto</option><option value="bank">Bank Wire</option></select></div>
        <button onclick="handleWithdraw()" class="btn btn-ghost w-full" id="withdraw-btn">Request Payout</button>
        <div id="withdraw-result" class="hidden"></div>
      </div>
      <p class="text-xs mt-4" style="color:var(--text-muted)">Withdrawals require admin approval.</p>
    </div>
  </div>`
}

async function handleDeposit(){
  const btn=document.getElementById('deposit-btn');btn.disabled=true;btn.textContent='Processing...';
  const amount=document.getElementById('deposit-amount').value;
  const coin=document.getElementById('deposit-coin').value;
  const data=await api('/wallet/deposit',{method:'POST',body:JSON.stringify({amount_usd:amount,coin})});
  btn.disabled=false;btn.textContent='Generate Invoice';
  const r=document.getElementById('deposit-result');r.classList.remove('hidden');
  if(!data||data._status){r.innerHTML=`<p class="text-sm text-red-400 mt-2 font-semibold">${data?.message||'Deposit failed.'}</p>`;return}
  r.innerHTML=`<div class="mt-3 p-3 rounded-lg" style="background:var(--surface-1);border:1px solid var(--border-subtle)">
    <p class="text-sm font-semibold text-green-400 mb-2">Invoice Created</p>
    <p class="text-xs" style="color:var(--text-muted)">Send: <strong style="color:var(--text-primary)">${data.deposit_invoice.amount_coin} ${data.deposit_invoice.coin.toUpperCase()}</strong></p>
    <p class="text-xs font-mono p-2 mt-2 rounded select-all break-all" style="background:var(--surface-0);border:1px solid var(--border-subtle);color:var(--text-primary)">${data.deposit_invoice.pay_address}</p>
    <p class="text-xs mt-2" style="color:var(--text-muted)">Status: <span class="badge badge-yellow">${data.deposit_invoice.status}</span></p>
    <p class="text-xs mt-1" style="color:var(--text-muted)">Expires: ${new Date(data.deposit_invoice.expires_at).toLocaleString()}</p>
  </div>`;showToast('Invoice created!','success')
}

async function handleWithdraw(){
  const btn=document.getElementById('withdraw-btn');btn.disabled=true;btn.textContent='Processing...';
  const amount=document.getElementById('withdraw-amount').value;
  const destination=document.getElementById('withdraw-destination').value;
  const method=document.getElementById('withdraw-method').value;
  if(!destination){showToast('Address required.','error');btn.disabled=false;btn.textContent='Request Payout';return}
  const data=await api('/wallet/withdraw',{method:'POST',body:JSON.stringify({amount,destination,provider_method:method})});
  btn.disabled=false;btn.textContent='Request Payout';
  const r=document.getElementById('withdraw-result');r.classList.remove('hidden');
  if(!data||data._status){r.innerHTML=`<p class="text-sm text-red-400 mt-2 font-semibold">${data?.message||'Withdrawal failed.'}</p>`;return}
  r.innerHTML=`<div class="mt-3 p-3 rounded-lg" style="background:var(--surface-1);border:1px solid var(--border-subtle)">
    <p class="text-sm font-semibold text-yellow-400">Payout Pending</p>
    <p class="text-xs mt-1" style="color:var(--text-secondary)">Amount: <strong class="gold-text">$${parseFloat(data.withdrawal.amount).toFixed(2)}</strong></p>
    <p class="text-xs" style="color:var(--text-muted)">Status: <span class="badge badge-yellow">${data.withdrawal.status}</span></p>
  </div>`;
  const w=await api('/wallet');if(w)document.getElementById('wallet-balance').textContent=`$${parseFloat(w.balance).toFixed(2)}`;
  showToast('Payout requested!','info')
}

/* ── Games Lobby ── */
async function renderGames(app){
  renderLayout(app);const content=document.getElementById('page-content');
  content.innerHTML='<div class="spinner"></div>';
  const gamesData=await api('/games');
  const games=Array.isArray(gamesData)?gamesData:(gamesData?.length!==undefined?gamesData:[]);
  content.innerHTML=`
  <div class="page-header"><h1 class="page-title gold-text">Games</h1><p class="page-subtitle">Choose a game to play</p></div>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-6" id="games-lobby">${games.length===0?`<div class="empty-state col-span-full">${icons.empty}<p>No games currently available.</p></div>`:''}</div>`;
  const lobby=document.getElementById('games-lobby');
  games.forEach(g=>{
    const isDice=g.slug==='dice';const card=document.createElement('div');
    card.className=`card cursor-pointer ${isDice?'card-gold':'card-silver'}`;
    card.innerHTML=`
    <div class="flex items-start justify-between mb-3">
      <div><h3 class="text-lg font-bold ${isDice?'gold-text':'silver-text'}">${g.name}</h3><p class="text-xs mt-1" style="color:var(--text-muted)">${g.config?.description||'Casino game'}</p></div>
      <span class="badge badge-green">${g.rtp_percentage}% RTP</span>
    </div>
    <div class="flex items-center justify-between pt-3 border-t">
      <span class="text-xs font-mono" style="color:var(--text-muted)">${g.config?.min_bet?`$${g.config.min_bet} - $${g.config.max_bet}`:'$0.10 - $5000'}</span>
      <button onclick="navigate('game-play',{gameId:'${g.slug}'})" class="btn ${isDice?'btn-gold':'btn-silver'} btn-sm">Play</button>
    </div>`;
    lobby.appendChild(card)
  })
}

/* ── Game Play ── */
async function renderGamePlay(app,params){
  renderLayout(app);const content=document.getElementById('page-content');
  const gamesData=await api('/games');
  const games=Array.isArray(gamesData)?gamesData:(gamesData?.length!==undefined?gamesData:[]);
  const game=games.find(g=>g.slug===params.gameId)||games[0];
  if(!game){content.innerHTML='<div class="empty-state"><p>Game not found.</p></div>';return}
  const walletData=await api('/wallet');const balance=walletData?.balance||'0.00';
  const isDice=game.slug==='dice';

  content.innerHTML=`
  <div class="flex items-center gap-3 mb-6">
    <button onclick="navigate('games')" class="btn btn-ghost btn-icon">${icon('back')}</button>
    <h1 class="text-xl font-bold ${isDice?'gold-text':'silver-text'}">${game.name}</h1>
  </div>
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2"><div class="card ${isDice?'card-gold':'card-silver'}">${isDice?renderDiceUI(game):renderSpinUI(game)}</div></div>
    <div>
      <div class="card mb-4"><div class="stat-label">Balance</div><div class="text-2xl font-black gold-text mt-1" id="game-balance-display">$${parseFloat(balance).toFixed(2)}</div></div>
      <div class="card"><div class="stat-label mb-3">Recent Bets</div><div id="bet-history-list" class="space-y-3 text-xs font-mono max-h-60 overflow-y-auto"></div></div>
    </div>
  </div>`;
  if(isDice){setTimeout(()=>{const t=document.getElementById('dice-target'),c=document.getElementById('dice-condition');if(t&&c){t.addEventListener('input',updateDiceSliderZones);c.addEventListener('change',updateDiceSliderZones);updateDiceSliderZones()}},50)}
}

function renderDiceUI(game){return`
  <div class="text-center mb-6">
    <div class="roll-display gold-text" id="dice-roll">50.00</div>
    <div id="dice-result" class="text-sm font-semibold mt-1" style="min-height:1.5rem;color:var(--text-muted)">Ready to roll</div>
  </div>
  <div class="dice-slider-container">
    <div class="dice-slider-track">
      <div class="dice-slider-zone dice-slider-zone-green absolute top-0 bottom-0" id="dice-zone-green"></div>
      <div class="dice-slider-zone dice-slider-zone-red absolute top-0 bottom-0" id="dice-zone-red"></div>
    </div>
    <div class="dice-slider-marker" id="dice-marker" style="left:50%"></div>
  </div>
  <div class="grid grid-cols-2 gap-4 mb-4">
    <div><label class="label">Target (1-99)</label><input id="dice-target" type="number" value="50" min="1" max="99" class="input"></div>
    <div><label class="label">Condition</label><select id="dice-condition" class="select"><option value="under">Under</option><option value="over">Over</option></select></div>
  </div>
  <div class="grid grid-cols-2 gap-4 mb-6">
    <div><label class="label">Bet Amount ($)</label><input id="dice-amount" type="number" value="10.00" min="0.10" max="5000" step="1.00" class="input"></div>
    <div><label class="label">Client Seed</label><input id="dice-seed" type="text" value="${randomSeed()}" class="input font-mono text-xs"></div>
  </div>
  <button onclick="placeDiceBet(${game.id})" id="dice-btn" class="btn btn-gold w-full py-3">Roll Dice</button>
  <div id="dice-proof" class="hidden mt-4"></div>`}

function updateDiceSliderZones(){
  const t=document.getElementById('dice-target'),c=document.getElementById('dice-condition'),g=document.getElementById('dice-zone-green'),r=document.getElementById('dice-zone-red');
  if(!t||!c||!g||!r)return;const val=parseInt(t.value)||50;const cond=c.value;
  if(cond==='under'){g.style.left='0%';g.style.width=`${val}%`;r.style.left=`${val}%`;r.style.width=`${100-val}%`}
  else{r.style.left='0%';r.style.width=`${val}%`;g.style.left=`${val}%`;g.style.width=`${100-val}%`}
}

function renderSpinUI(game){
  const sectors=game.config?.sectors||8;
  let btns='';for(let i=1;i<=sectors;i++)btns+=`<button onclick="document.getElementById('spin-sector').value=${i}" class="sector-btn">${i}</button>`;
  let sectorDivs='';for(let i=1;i<=sectors;i++)sectorDivs+=`<div class="wheel-sector-num" style="--angle:${(i-1)*(360/sectors)+(180/sectors)}deg">${i}</div>`;
  return`
  <div class="text-center mb-2">
    <div class="roll-display silver-text" id="spin-result">1</div>
    <div id="spin-status" class="text-sm font-semibold mt-1" style="min-height:1.5rem;color:var(--text-muted)">Place your bet</div>
  </div>
  <div class="wheel-outer">
    <div class="wheel-pointer"></div>
    <div class="spin-wheel" id="spin-wheel">${sectorDivs}</div>
  </div>
  <div class="mb-4">
    <label class="label">Target Sector (1-${sectors})</label>
    <div class="flex gap-2 flex-wrap mb-3">${btns}</div>
    <input id="spin-sector" type="number" value="1" min="1" max="${sectors}" class="input">
  </div>
  <div class="grid grid-cols-2 gap-4 mb-6">
    <div><label class="label">Bet Amount ($)</label><input id="spin-amount" type="number" value="10.00" min="0.10" max="5000" step="1.00" class="input"></div>
    <div><label class="label">Client Seed</label><input id="spin-seed" type="text" value="${randomSeed()}" class="input font-mono text-xs"></div>
  </div>
  <button onclick="placeSpinBet(${game.id})" id="spin-btn" class="btn btn-silver w-full py-3">Spin Wheel</button>
  <div id="spin-proof" class="hidden mt-4"></div>`
}

function randomSeed(){return Math.random().toString(36).substring(2,10)}

/* ── Dice Bet Logic ── */
async function placeDiceBet(gameId){
  const btn=document.getElementById('dice-btn'),amount=document.getElementById('dice-amount').value,target=parseInt(document.getElementById('dice-target').value),condition=document.getElementById('dice-condition').value,clientSeed=document.getElementById('dice-seed').value;
  btn.disabled=true;btn.textContent='Rolling...';
  const rollInterval=setInterval(()=>{const el=document.getElementById('dice-roll');if(el){el.textContent=(Math.random()*100).toFixed(2);el.style.color='var(--text-muted)'}},50);
  const data=await api(`/games/${gameId}/bet`,{method:'POST',body:JSON.stringify({bet_amount:amount,client_seed:clientSeed,payload:{target,condition}})});
  clearInterval(rollInterval);btn.disabled=false;btn.textContent='Roll Dice';
  if(!data||data._status)return showToast(data?.message||'Bet failed.','error');
  const roll=data.outcome?.state?.roll,isWin=data.outcome?.state?.is_win;
  document.getElementById('dice-roll').textContent=roll!==undefined?roll.toFixed(2):'--';
  document.getElementById('dice-roll').style.color=isWin?'#4ade80':'#f87171';
  document.getElementById('dice-marker').style.left=`${roll}%`;
  const re=document.getElementById('dice-result');
  re.textContent=isWin?`Win! +$${parseFloat(data.bet.payout_amount).toFixed(2)}`:'Lost';
  re.style.color=isWin?'#4ade80':'#f87171';
  if(data.bet.result?.server_seed){const p=document.getElementById('dice-proof');p.classList.remove('hidden');p.innerHTML=`<div class="p-3 rounded-lg text-xs font-mono break-all" style="background:var(--surface-1);border:1px solid var(--border-subtle);color:var(--text-muted);line-height:1.6">Server: <span style="color:var(--text-primary)">${data.bet.result.server_seed}</span><br>Client: <span style="color:var(--text-primary)">${data.bet.client_seed}</span><br>Hash: <span style="color:var(--text-primary)">${data.bet.server_seed_hash}</span></div>`}
  const h=document.getElementById('bet-history-list'),e=document.createElement('div');
  e.className=`p-3 rounded flex items-center justify-between border-l-4 ${isWin?'border-green-500 text-green-400':'border-red-500 text-red-400'}`;
  e.style.background='var(--surface-1)';
  e.innerHTML=`<span>#${data.bet.id} Roll: ${roll?.toFixed(2)}</span><span>${isWin?'+$'+parseFloat(data.bet.payout_amount).toFixed(2):'-$'+parseFloat(amount).toFixed(2)}</span>`;
  h.prepend(e);if(h.children.length>12)h.removeChild(h.lastChild);
  const w=await api('/wallet');if(w)document.getElementById('game-balance-display').textContent=`$${parseFloat(w.balance).toFixed(2)}`
}

/* ── Spin Bet Logic ── */
async function placeSpinBet(gameId){
  const btn=document.getElementById('spin-btn'),amount=document.getElementById('spin-amount').value,sector=parseInt(document.getElementById('spin-sector').value),clientSeed=document.getElementById('spin-seed').value;
  btn.disabled=true;btn.textContent='Spinning...';
  const wheel=document.getElementById('spin-wheel');currentRotation+=1800;wheel.style.transform=`rotate(${currentRotation}deg)`;
  const data=await api(`/games/${gameId}/bet`,{method:'POST',body:JSON.stringify({bet_amount:amount,client_seed:clientSeed,payload:{sector}})});
  if(!data||data._status){btn.disabled=false;btn.textContent='Spin Wheel';return showToast(data?.message||'Spin failed.','error')}
  const s=data.outcome?.state,isWin=s?.is_win,sectors=8,sectorAngle=360/sectors,landed=s?.landed_sector||1;
  const targetOffset=360-((landed-1)*sectorAngle+(sectorAngle/2));
  currentRotation=Math.ceil(currentRotation/360)*360+targetOffset+1440;
  setTimeout(()=>{
    wheel.style.transform=`rotate(${currentRotation}deg)`;
    setTimeout(()=>{
      btn.disabled=false;btn.textContent='Spin Wheel';
      document.getElementById('spin-result').textContent=`Sector ${landed}`;
      document.getElementById('spin-result').style.color=isWin?'#4ade80':'#f87171';
      const st=document.getElementById('spin-status');
      st.textContent=isWin?`Win! +$${parseFloat(data.bet.payout_amount).toFixed(2)}`:`Lost. Picked ${s?.player_sector}, landed ${landed}`;
      st.style.color=isWin?'#4ade80':'#f87171';
      if(data.bet.result?.server_seed){const p=document.getElementById('spin-proof');p.classList.remove('hidden');p.innerHTML=`<div class="p-3 rounded-lg text-xs font-mono break-all" style="background:var(--surface-1);border:1px solid var(--border-subtle);color:var(--text-muted);line-height:1.6">Server: <span style="color:var(--text-primary)">${data.bet.result.server_seed}</span><br>Client: <span style="color:var(--text-primary)">${data.bet.client_seed}</span></div>`}
      const h=document.getElementById('bet-history-list'),e=document.createElement('div');
      e.className=`p-3 rounded flex items-center justify-between border-l-4 ${isWin?'border-green-500 text-green-400':'border-red-500 text-red-400'}`;
      e.style.background='var(--surface-1)';
      e.innerHTML=`<span>#${data.bet.id} Sector ${landed}</span><span>${isWin?'+$'+parseFloat(data.bet.payout_amount).toFixed(2):'-$'+parseFloat(amount).toFixed(2)}</span>`;
      h.prepend(e);if(h.children.length>12)h.removeChild(h.lastChild);
      api('/wallet').then(w=>{if(w)document.getElementById('game-balance-display').textContent=`$${parseFloat(w.balance).toFixed(2)}`})
    },4500)
  },100)
}

/* ── Profile / Settings ── */
async function renderProfile(app){
  renderLayout(app);const content=document.getElementById('page-content');
  content.innerHTML='<div class="spinner"></div>';
  const[rData,seData]=await Promise.all([api('/user/restrictions'),api('/user/self-exclusion')]);
  const restrictions=rData?.restrictions,selfExcl=seData?.intervention,selfExcluded=seData?.self_excluded;
  content.innerHTML=`
  <div class="page-header"><h1 class="page-title gold-text">Settings</h1><p class="page-subtitle">Account and responsible gaming</p></div>
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="card"><h3 class="font-bold mb-4">Profile</h3><div class="space-y-4">
      <div><label class="label">Name</label><input id="profile-name" type="text" class="input" value="${user?.name||''}"></div>
      <div><label class="label">Email</label><input id="profile-email" type="email" class="input" value="${user?.email||''}"></div>
      <button onclick="handleUpdateProfile()" class="btn btn-gold w-full" id="profile-btn">Update Profile</button>
    </div></div>
    <div class="card"><h3 class="font-bold mb-4">Security</h3><div class="space-y-4">
      <div><label class="label">Current Password</label><input id="pw-current" type="password" class="input"></div>
      <div><label class="label">New Password</label><input id="pw-new" type="password" class="input"></div>
      <div><label class="label">Confirm New Password</label><input id="pw-confirm" type="password" class="input"></div>
      <button onclick="handleUpdatePassword()" class="btn btn-ghost w-full" id="pw-btn">Change Password</button>
    </div></div>
    <div class="card"><h3 class="font-bold mb-4">Responsible Gaming</h3><div class="space-y-4">
      <div><label class="label">Daily Deposit Limit ($)</label><input id="limit-deposit" type="number" class="input" value="${restrictions?.daily_deposit_limit||''}" placeholder="No limit" min="0"></div>
      <div><label class="label">Daily Bet Limit ($)</label><input id="limit-bet" type="number" class="input" value="${restrictions?.daily_bet_limit||''}" placeholder="No limit" min="0"></div>
      <div><label class="label">Daily Loss Limit ($)</label><input id="limit-loss" type="number" class="input" value="${restrictions?.daily_loss_limit||''}" placeholder="No limit" min="0"></div>
      <button onclick="handleUpdateLimits()" class="btn btn-gold w-full" id="limits-btn">Save Limits</button>
    </div></div>
    <div class="card card-gold"><h3 class="font-bold mb-4">Self-Exclusion</h3>
    ${selfExcluded?`<div class="p-3 rounded-lg" style="background:var(--surface-1);border:1px solid var(--border-gold)"><p class="text-sm font-semibold text-yellow-400">Self-Exclusion Active</p><p class="text-xs mt-1" style="color:var(--text-secondary)">Until: <strong>${new Date(selfExcl.ends_at).toLocaleDateString()}</strong></p>${selfExcl.payload?.reason?`<p class="text-xs mt-1" style="color:var(--text-muted)">Reason: ${selfExcl.payload.reason}</p>`:''}</div>`:
    `<p class="text-sm mb-4" style="color:var(--text-secondary)">Temporarily suspend your account.</p><div class="space-y-4">
      <div><label class="label">Period (days, 1-365)</label><input id="excl-days" type="number" class="input" value="30" min="1" max="365"></div>
      <div><label class="label">Reason</label><input id="excl-reason" type="text" class="input" placeholder="Optional"></div>
      <button onclick="handleSelfExclude()" class="btn btn-danger w-full" id="excl-btn">Suspend Account</button>
    </div>`}
    </div>
  </div>`
}

async function handleUpdateProfile(){const btn=document.getElementById('profile-btn');btn.disabled=true;btn.textContent='Saving...';const n=document.getElementById('profile-name').value,e=document.getElementById('profile-email').value;const data=await api('/user/profile',{method:'PATCH',body:JSON.stringify({name:n,email:e})});btn.disabled=false;btn.textContent='Update Profile';if(data&&!data._status){user=data.user;showToast('Profile updated!','success')}else showToast(data?.message||'Update failed.','error')}
async function handleUpdatePassword(){const btn=document.getElementById('pw-btn');btn.disabled=true;btn.textContent='Updating...';const cur=document.getElementById('pw-current').value,pw=document.getElementById('pw-new').value,conf=document.getElementById('pw-confirm').value;if(pw!==conf){showToast('Passwords do not match.','error');btn.disabled=false;btn.textContent='Change Password';return}const data=await api('/user/password',{method:'PATCH',body:JSON.stringify({current_password:cur,password:pw,password_confirmation:conf})});btn.disabled=false;btn.textContent='Change Password';if(data&&!data._status){showToast('Password changed!','success');document.getElementById('pw-current').value='';document.getElementById('pw-new').value='';document.getElementById('pw-confirm').value=''}else showToast(data?.message||'Failed.','error')}
async function handleUpdateLimits(){const btn=document.getElementById('limits-btn');btn.disabled=true;btn.textContent='Saving...';const d=document.getElementById('limit-deposit').value,b=document.getElementById('limit-bet').value,l=document.getElementById('limit-loss').value;const body={};if(d)body.daily_deposit_limit=d;if(b)body.daily_bet_limit=b;if(l)body.daily_loss_limit=l;const data=await api('/user/restrictions',{method:'PATCH',body:JSON.stringify(body)});btn.disabled=false;btn.textContent='Save Limits';if(data&&!data._status)showToast('Limits saved!','success');else showToast(data?.message||'Failed.','error')}
async function handleSelfExclude(){const btn=document.getElementById('excl-btn');btn.disabled=true;btn.textContent='Processing...';const days=document.getElementById('excl-days').value,reason=document.getElementById('excl-reason').value;const data=await api('/user/self-exclusion',{method:'POST',body:JSON.stringify({days:parseInt(days),reason})});btn.disabled=false;btn.textContent='Suspend Account';if(data&&!data._status){showToast('Account suspended.','info');navigate('profile')}else showToast(data?.message||'Failed.','error')}

/* ── Admin Panel ── */
let adminTab='users';
async function renderAdmin(app){
  renderLayout(app);const content=document.getElementById('page-content');
  const adminCheck=await api('/admin/users');
  if(!adminCheck||adminCheck._status){document.getElementById('admin-nav').style.display='none';content.innerHTML='<div class="empty-state"><p>Access denied.</p></div>';return}
  document.getElementById('admin-nav').style.display='flex';
  content.innerHTML=`
  <div class="page-header"><h1 class="page-title gold-text">Admin</h1><p class="page-subtitle">Management dashboard</p></div>
  <div class="tabs">
    <button class="tab active" data-tab="users" onclick="switchAdminTab('users')">Users</button>
    <button class="tab" data-tab="withdrawals" onclick="switchAdminTab('withdrawals')">Withdrawals</button>
    <button class="tab" data-tab="risk" onclick="switchAdminTab('risk')">Risk</button>
    <button class="tab" data-tab="interventions" onclick="switchAdminTab('interventions')">Interventions</button>
    <button class="tab" data-tab="games" onclick="switchAdminTab('games')">Games</button>
  </div>
  <div id="admin-content"></div>`;
  switchAdminTab('users')
}

function switchAdminTab(tab){
  adminTab=tab;
  document.querySelectorAll('.tab').forEach(t=>t.classList.toggle('active',t.dataset.tab===tab));
  const content=document.getElementById('admin-content');content.innerHTML='<div class="spinner"></div>';
  if(tab==='users')renderAdminUsers(content);else if(tab==='withdrawals')renderAdminWithdrawals(content);
  else if(tab==='risk')renderAdminRisk(content);else if(tab==='interventions')renderAdminInterventions(content);
  else if(tab==='games')renderAdminGames(content)
}

async function renderAdminUsers(content){
  const data=await api('/admin/users');const users=data?.users||[];
  content.innerHTML=`<div class="table-wrapper"><table><thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Status</th><th>Joined</th><th>Actions</th></tr></thead><tbody>${users.map(u=>`<tr>
    <td class="font-mono text-xs">${u.id}</td>
    <td class="font-semibold" style="color:var(--text-primary)">${u.name}</td>
    <td class="font-mono text-xs">${u.email}</td>
    <td><span class="badge ${u.status==='active'?'badge-green':'badge-red'}">${u.status}</span></td>
    <td class="text-xs">${new Date(u.created_at).toLocaleDateString()}</td>
    <td class="flex gap-2"><button onclick="openUserInterventions(${u.id})" class="btn btn-gold btn-sm">Limits</button>${u.status==='active'?`<button onclick="adminDisableUser(${u.id})" class="btn btn-ghost btn-sm">Disable</button>`:''}<button onclick="adminDeleteUser(${u.id})" class="btn btn-danger btn-sm">Delete</button></td>
  </tr>`).join('')}</tbody></table></div>`
}

function openUserInterventions(id){adminSelectedInterventionUserId=id;switchAdminTab('interventions')}

async function adminDisableUser(id){
  showModal({title:'Disable User',description:'Provide an optional reason for disabling this account.',showInput:true,inputLabel:'Reason',inputPlaceholder:'Optional reason',confirmText:'Disable',confirmClass:'btn-danger',onConfirm:async(reason)=>{
    const data=await api(`/admin/users/${id}/disable`,{method:'PATCH',body:JSON.stringify({reason:reason||undefined})});
    if(data&&!data._status){showToast('User disabled.','info');switchAdminTab('users')}else showToast(data?.message||'Failed.','error')
  }})
}

async function adminDeleteUser(id){
  showModal({title:'Delete User',description:'This action is permanent and cannot be undone.',confirmText:'Delete',confirmClass:'btn-danger',onConfirm:async()=>{
    const data=await api(`/admin/users/${id}`,{method:'DELETE'});
    if(data&&!data._status){showToast('User deleted.','info');switchAdminTab('users')}else showToast(data?.message||'Failed.','error')
  }})
}

async function renderAdminWithdrawals(content){
  const data=await api('/admin/withdrawals');const w=data?.withdrawals||[];
  content.innerHTML=`<div class="table-wrapper"><table><thead><tr><th>ID</th><th>User</th><th>Amount</th><th>Status</th><th>Address</th><th>Date</th><th>Actions</th></tr></thead><tbody>
  ${w.length===0?'<tr><td colspan="7" class="text-center py-4" style="color:var(--text-muted)">No pending withdrawals</td></tr>':''}
  ${w.map(wi=>`<tr>
    <td class="font-mono text-xs">${wi.id}</td>
    <td class="text-xs">User #${wi.user_id}</td>
    <td class="font-bold gold-text">$${parseFloat(wi.amount).toFixed(2)}</td>
    <td><span class="badge badge-yellow">${wi.status}</span></td>
    <td class="font-mono text-xs truncate" style="max-width:150px">${wi.meta?.destination||'--'}</td>
    <td class="text-xs">${new Date(wi.created_at).toLocaleString()}</td>
    <td class="flex gap-2"><button onclick="adminApproveWithdrawal(${wi.id})" class="btn btn-gold btn-sm">Approve</button><button onclick="adminRejectWithdrawal(${wi.id})" class="btn btn-ghost btn-sm">Reject</button></td>
  </tr>`).join('')}</tbody></table></div>`
}

async function adminApproveWithdrawal(id){
  showModal({title:'Approve Withdrawal',description:'Optionally enter a transaction hash.',showInput:true,inputLabel:'TX Hash',inputPlaceholder:'Optional',confirmText:'Approve',confirmClass:'btn-gold',onConfirm:async(txHash)=>{
    const data=await api(`/admin/withdrawals/${id}/approve`,{method:'PATCH',body:JSON.stringify({tx_hash:txHash||undefined})});
    if(data&&!data._status){showToast('Withdrawal approved!','success');switchAdminTab('withdrawals')}else showToast(data?.message||'Failed.','error')
  }})
}

async function adminRejectWithdrawal(id){
  showModal({title:'Reject Withdrawal',description:'A reason is required for audit purposes.',showInput:true,inputLabel:'Reason',inputPlaceholder:'Rejection reason',confirmText:'Reject',confirmClass:'btn-danger',onConfirm:async(reason)=>{
    if(!reason)return showToast('Reason required.','error');
    const data=await api(`/admin/withdrawals/${id}/reject`,{method:'PATCH',body:JSON.stringify({reason})});
    if(data&&!data._status){showToast('Withdrawal rejected.','info');switchAdminTab('withdrawals')}else showToast(data?.message||'Failed.','error')
  }})
}

async function renderAdminRisk(content){
  const[eData,iData]=await Promise.all([api('/admin/risk-events'),api('/admin/interventions')]);
  const events=eData?.risk_events||[],interventions=iData?.interventions||[];
  content.innerHTML=`
  <div class="mb-6">
    <h3 class="font-bold mb-3">Active Interventions (${interventions.length})</h3>
    ${interventions.length===0?`<div class="p-4 rounded-lg text-sm text-center" style="background:var(--surface-1);border:1px solid var(--border-subtle);color:var(--text-muted)">All accounts within normal limits.</div>`:''}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">${interventions.map(i=>`<div class="card card-gold">
      <div class="flex items-center justify-between mb-2"><span class="badge badge-red">${i.type}</span><span class="text-xs" style="color:var(--text-secondary)">#${i.user_id} ${i.user?.name||''}</span></div>
      <p class="text-xs" style="color:var(--text-secondary)">${i.payload?.message||'Active hold'}</p>
      <p class="text-xs mt-2" style="color:var(--text-muted)">Ends: ${new Date(i.ends_at).toLocaleString()}</p>
    </div>`).join('')}</div>
  </div>
  <div>
    <h3 class="font-bold mb-3">Risk Events</h3>
    <div class="table-wrapper"><table><thead><tr><th>ID</th><th>User</th><th>Type</th><th>Score</th><th>Value</th><th>Date</th></tr></thead><tbody>
    ${events.length===0?'<tr><td colspan="6" class="text-center py-4" style="color:var(--text-muted)">No risk events</td></tr>':''}
    ${events.map(e=>`<tr>
      <td class="font-mono text-xs">${e.id}</td>
      <td class="text-xs" style="color:var(--text-primary)">${e.user?.name||`#${e.user_id}`}</td>
      <td><span class="badge ${e.type==='chasing_losses'?'badge-red':e.type==='deposit_spike'?'badge-yellow':'badge-blue'}">${e.type}</span></td>
      <td class="font-semibold" style="color:var(--text-primary)">+${e.score_delta}</td>
      <td class="font-mono text-xs">${e.payload?.bet_amount?'$'+parseFloat(e.payload.bet_amount).toFixed(2):'--'}</td>
      <td class="text-xs">${new Date(e.created_at).toLocaleString()}</td>
    </tr>`).join('')}</tbody></table></div>
  </div>`
}

async function renderAdminInterventions(content){
  const usersData=await api('/admin/users');const users=usersData?.users||[];
  if(users.length&&!users.some(u=>String(u.id)===String(adminSelectedInterventionUserId)))adminSelectedInterventionUserId=users[0].id;
  const selected=users.find(u=>String(u.id)===String(adminSelectedInterventionUserId));
  const iData=selected?await api(`/admin/users/${selected.id}/interventions`):{interventions:[]};
  const interventions=iData?.interventions||[];
  content.innerHTML=`
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="card card-gold">
      <h3 class="font-bold mb-3">Target User</h3>
      <div class="form-group">
        <label class="label">User</label>
        <select id="admin-intervention-user" class="input" onchange="adminSelectedInterventionUserId=this.value;renderAdminInterventions(document.getElementById('admin-content'))">
          ${users.map(u=>`<option value="${u.id}" ${String(u.id)===String(adminSelectedInterventionUserId)?'selected':''}>#${u.id} ${esc(u.name)} (${esc(u.email)})</option>`).join('')}
        </select>
      </div>
      ${selected?`<p class="text-xs" style="color:var(--text-secondary)">Selected account: <strong style="color:var(--text-primary)">${esc(selected.name)}</strong></p>`:`<p class="text-sm" style="color:var(--text-muted)">No users available.</p>`}
    </div>
    <div class="card card-gold" style="grid-column:span 2">
      <h3 class="font-bold mb-3">Apply Intervention</h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="form-group"><label class="label">Type</label><select id="admin-intervention-type" class="input" onchange="handleAdminInterventionTypeChange()">
          <option value="admin_bet_block">Betting block</option>
          <option value="admin_deposit_block">Deposit block</option>
          <option value="admin_win_limit">Win count limit</option>
          <option value="admin_cool_off">Cool-off</option>
        </select></div>
        <div class="form-group"><label class="label">Ends At</label><input id="admin-intervention-ends" type="datetime-local" class="input"></div>
        <div class="form-group admin-win-limit-fields"><label class="label">Max Wins</label><input id="admin-intervention-max-wins" type="number" class="input" min="1" value="5"></div>
        <div class="form-group admin-win-limit-fields"><label class="label">Window</label><select id="admin-intervention-window" class="input"><option value="day">Calendar day</option><option value="24h">Rolling 24h</option></select></div>
        <div class="form-group" style="grid-column:1/-1"><label class="label">Reason</label><input id="admin-intervention-reason" type="text" class="input" placeholder="Required audit reason"></div>
      </div>
      <button onclick="handleCreateIntervention()" class="btn btn-gold w-full" ${selected?'':'disabled'}>Apply Intervention</button>
    </div>
  </div>
  <div>
    <h3 class="font-bold mb-3">Intervention History ${selected?`for #${selected.id}`:''}</h3>
    <div class="table-wrapper"><table><thead><tr><th>ID</th><th>Type</th><th>Status</th><th>Details</th><th>Ends</th><th>Actions</th></tr></thead><tbody>
      ${interventions.length===0?'<tr><td colspan="6" class="text-center py-4" style="color:var(--text-muted)">No interventions for this user</td></tr>':''}
      ${interventions.map(i=>renderInterventionRow(i)).join('')}
    </tbody></table></div>
  </div>`;
  handleAdminInterventionTypeChange()
}

function renderInterventionRow(i){
  const payload=i.payload||{},details=[];
  if(payload.max_wins)details.push(`max wins: ${payload.max_wins}`);
  if(payload.window)details.push(`window: ${payload.window}`);
  if(payload.reason)details.push(`reason: ${esc(payload.reason)}`);
  if(payload.revoke_reason)details.push(`revoked: ${esc(payload.revoke_reason)}`);
  const badge=i.status==='active'?'badge-red':i.status==='revoked'?'badge-gray':'badge-yellow';
  return `<tr>
    <td class="font-mono text-xs">${i.id}</td>
    <td><span class="badge badge-gold">${esc(interventionLabel(i.type))}</span></td>
    <td><span class="badge ${badge}">${esc(i.status)}</span></td>
    <td class="text-xs" style="color:var(--text-secondary)">${details.length?details.join('<br>'):'--'}</td>
    <td class="text-xs">${fmtDate(i.ends_at)}</td>
    <td>${i.status==='active'?`<button onclick="handleRevokeIntervention(${i.id})" class="btn btn-ghost btn-sm">Revoke</button>`:'<span class="text-xs" style="color:var(--text-muted)">Archived</span>'}</td>
  </tr>`
}

function handleAdminInterventionTypeChange(){
  const type=document.getElementById('admin-intervention-type')?.value;
  document.querySelectorAll('.admin-win-limit-fields').forEach(el=>el.style.display=type==='admin_win_limit'?'block':'none')
}

async function handleCreateIntervention(){
  const userId=document.getElementById('admin-intervention-user')?.value;
  const type=document.getElementById('admin-intervention-type')?.value;
  const reason=document.getElementById('admin-intervention-reason')?.value.trim();
  const endsAt=document.getElementById('admin-intervention-ends')?.value;
  if(!userId)return showToast('Select a user first.','error');
  if(!reason)return showToast('Reason is required.','error');
  const body={type,reason};
  if(endsAt)body.ends_at=endsAt;
  if(type==='admin_win_limit'){
    const maxWins=parseInt(document.getElementById('admin-intervention-max-wins')?.value,10);
    if(!maxWins||maxWins<1)return showToast('Max wins must be at least 1.','error');
    body.payload={max_wins:maxWins,window:document.getElementById('admin-intervention-window')?.value||'day'}
  }
  const data=await api(`/admin/users/${userId}/interventions`,{method:'POST',body:JSON.stringify(body)});
  if(data&&!data._status){showToast('Intervention applied.','success');renderAdminInterventions(document.getElementById('admin-content'))}
  else showToast(data?.message||'Failed to apply intervention.','error')
}

async function handleRevokeIntervention(id){
  showModal({title:'Revoke Intervention',description:'The historical record will remain archived.',showInput:true,inputLabel:'Reason',inputPlaceholder:'Required revoke reason',confirmText:'Revoke',confirmClass:'btn-danger',onConfirm:async(reason)=>{
    if(!reason)return showToast('Reason is required.','error');
    const data=await api(`/admin/interventions/${id}/revoke`,{method:'PATCH',body:JSON.stringify({reason})});
    if(data&&!data._status){showToast('Intervention revoked.','info');renderAdminInterventions(document.getElementById('admin-content'))}
    else showToast(data?.message||'Failed to revoke intervention.','error')
  }})
}

async function renderAdminGames(content){
  const data=await api('/admin/games');const games=data?.games||[];
  content.innerHTML=`<div class="table-wrapper"><table><thead><tr><th>ID</th><th>Name</th><th>Slug</th><th>Status</th><th>RTP</th><th>Actions</th></tr></thead><tbody>
  ${games.map(g=>`<tr>
    <td class="font-mono text-xs">${g.id}</td>
    <td class="font-semibold" style="color:var(--text-primary)">${g.name}</td>
    <td class="font-mono text-xs">${g.slug}</td>
    <td><span class="badge badge-green">${g.status}</span></td>
    <td class="font-mono font-bold" style="color:var(--text-primary)" id="rtp-${g.id}">${g.rtp_percentage}%</td>
    <td class="flex gap-2"><button onclick="adminEditRtp(${g.id})" class="btn btn-gold btn-sm">Set RTP</button><button onclick="adminToggleStatus(${g.id},'${g.status}')" class="btn btn-ghost btn-sm">${g.status==='active'?'Deactivate':'Activate'}</button></td>
  </tr>`).join('')}</tbody></table></div>`
}

async function adminEditRtp(gameId){
  showModal({title:'Set RTP',description:'Enter the target RTP percentage (1-99.99).',showInput:true,inputLabel:'RTP %',inputPlaceholder:'e.g. 95.00',confirmText:'Update',confirmClass:'btn-gold',onConfirm:async(val)=>{
    if(!val)return;
    const data=await api(`/admin/games/${gameId}/rtp`,{method:'PATCH',body:JSON.stringify({rtp_percentage:parseFloat(val)})});
    if(data&&!data._status){showToast(`RTP updated to ${val}%`,'success');document.getElementById(`rtp-${gameId}`).textContent=`${parseFloat(val).toFixed(2)}%`}else showToast(data?.message||'Failed.','error')
  }})
}

async function adminToggleStatus(gameId,currentStatus){
  const newStatus=currentStatus==='active'?'inactive':'active';
  const data=await api(`/admin/games/${gameId}/status`,{method:'PATCH',body:JSON.stringify({status:newStatus})});
  if(data&&!data._status){showToast(`Game ${newStatus}.`,'info');switchAdminTab('games')}else showToast(data?.message||'Failed.','error')
}

/* ── Boot ── */
document.addEventListener('DOMContentLoaded',()=>{
  let hash=window.location.hash.slice(1)||(hasToken()?'dashboard':'login');let params={};
  if(hash.startsWith('game-play/')){params={gameId:hash.split('/')[1]};hash='game-play'}
  renderPage(hash,params)
})
