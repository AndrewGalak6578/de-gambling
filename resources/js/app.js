/* ═══════════════════════════════════════════════════════════════
   G-SHT High Roller VIP Club - Single Page Application
   ═══════════════════════════════════════════════════════════════ */
const API='/api/v1';
let token=localStorage.getItem('token'),user=null,currentPage='login',sidebarOpen=false,currentRotation=0;

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
  slots:`<svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><line x1="9" y1="4" x2="9" y2="20"/><line x1="15" y1="4" x2="15" y2="20"/><circle cx="6" cy="12" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="18" cy="12" r="1.5"/></svg>`,
  empty:`<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="8" y1="15" x2="16" y2="15"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>`
};
function icon(name,cls=''){return`<span class="nav-icon ${cls}">${icons[name]||''}</span>`}

/* ── Slot symbol metadata ── */
const SLOT_SYMBOLS={
  cherry: {icon:'🍒', label:'Cherry',  payout:10},
  lemon:  {icon:'🍋', label:'Lemon',   payout:16},
  bell:   {icon:'🔔', label:'Bell',    payout:27},
  star:   {icon:'⭐', label:'Star',    payout:55},
  diamond:{icon:'💎', label:'Diamond', payout:110},
  seven:  {icon:'7️⃣', label:'Seven',  payout:270},
  crown:  {icon:'👑', label:'Crown',   payout:1300}
};
const SLOT_SYMBOL_KEYS=['cherry','lemon','bell','star','diamond','seven','crown'];
const SLOT_REEL_LENGTH=25;
const SLOT_SYMBOL_HEIGHT=120;

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
function navigate(page,params={}){
  let hash=page;
  if(page==='game-play'&&params.gameId) hash=`game-play/${params.gameId}`;
  window.location.hash=hash;
  renderPage(page,params)
}
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

  const quickPlayHTML=`<div class="card card-gold"><h3 class="font-bold mb-3">Quick Play</h3>
    <div class="quick-play-grid">
      <div class="quick-play-tile" onclick="navigate('game-play',{gameId:'dice'})"><div class="quick-play-tile-icon">🎲</div>Dice</div>
      <div class="quick-play-tile" onclick="navigate('game-play',{gameId:'slots'})"><div class="quick-play-tile-icon">🎰</div>Royal Slots</div>
      <div class="quick-play-tile" onclick="navigate('game-play',{gameId:'spin-to-win'})"><div class="quick-play-tile-icon">🎡</div>Spin Wheel</div>
    </div></div>`;

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
      quickPlayHTML}
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
function gameCardClass(slug){
  if(slug==='dice') return 'card-gold';
  if(slug==='slots') return 'card-gold';
  return 'card-silver';
}
function gameAccent(slug){ return slug==='spin-to-win' ? 'silver' : 'gold'; }
function gameEmoji(slug){
  if(slug==='dice') return '🎲';
  if(slug==='slots') return '🎰';
  if(slug==='spin-to-win') return '🎡';
  return '🎮';
}

async function renderGames(app){
  renderLayout(app);const content=document.getElementById('page-content');
  content.innerHTML='<div class="spinner"></div>';
  const gamesData=await api('/games');
  const games=Array.isArray(gamesData)?gamesData:(gamesData?.length!==undefined?gamesData:[]);
  content.innerHTML=`
  <div class="page-header"><h1 class="page-title gold-text">Games</h1><p class="page-subtitle">Choose a game to play</p></div>
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="games-lobby">${games.length===0?`<div class="empty-state col-span-full">${icons.empty}<p>No games currently available.</p></div>`:''}</div>`;
  const lobby=document.getElementById('games-lobby');
  games.forEach(g=>{
    const accent=gameAccent(g.slug);
    const cardClass=gameCardClass(g.slug);
    const textClass=accent==='gold'?'gold-text':'silver-text';
    const btnClass=accent==='gold'?'btn-gold':'btn-silver';
    const card=document.createElement('div');
    card.className=`card cursor-pointer ${cardClass}`;
    card.innerHTML=`
    <div class="flex items-start justify-between mb-3">
      <div>
        <div style="font-size:2rem;line-height:1;margin-bottom:.5rem">${gameEmoji(g.slug)}</div>
        <h3 class="text-lg font-bold ${textClass}">${g.name}</h3>
        <p class="text-xs mt-1" style="color:var(--text-muted)">${g.config?.description||'Casino game'}</p>
      </div>
      <span class="badge badge-green">${g.rtp_percentage}% RTP</span>
    </div>
    <div class="flex items-center justify-between pt-3 border-t">
      <span class="text-xs font-mono" style="color:var(--text-muted)">${g.config?.min_bet?`$${g.config.min_bet} - $${g.config.max_bet}`:'$0.10 - $5000'}</span>
      <button onclick="navigate('game-play',{gameId:'${g.slug}'})" class="btn ${btnClass} btn-sm">Play</button>
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

  window.currentGameRtp=parseFloat(game.rtp_percentage)||95;

  let gameContent;
  if(game.slug==='dice') gameContent=renderDiceUI(game);
  else if(game.slug==='slots') gameContent=renderSlotsUI(game);
  else gameContent=renderSpinUI(game);

  const cardClass=gameCardClass(game.slug);
  const titleClass=gameAccent(game.slug)==='gold'?'gold-text':'silver-text';

  content.innerHTML=`
  <div class="flex items-center gap-3 mb-6">
    <button onclick="navigate('games')" class="btn btn-ghost btn-icon">${icon('back')}</button>
    <h1 class="text-xl font-bold ${titleClass}">${gameEmoji(game.slug)} ${game.name}</h1>
    <span class="badge badge-green" style="margin-left:auto">${game.rtp_percentage}% RTP</span>
  </div>
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2"><div class="card ${cardClass}">${gameContent}</div></div>
    <div>
      <div class="card mb-4"><div class="stat-label">Balance</div><div class="text-2xl font-black gold-text mt-1" id="game-balance-display">$${parseFloat(balance).toFixed(2)}</div></div>
      <div class="card"><div class="stat-label mb-3">Recent Bets</div><div id="bet-history-list" class="space-y-3 text-xs font-mono max-h-60 overflow-y-auto"></div></div>
    </div>
  </div>`;

  if(game.slug==='dice'){
    setTimeout(()=>{
      const t=document.getElementById('dice-target'),c=document.getElementById('dice-condition'),a=document.getElementById('dice-amount');
      if(t&&c){
        const refresh=()=>{updateDiceSliderZones();updateDiceStats()};
        t.addEventListener('input',refresh);
        c.addEventListener('change',refresh);
        if(a) a.addEventListener('input',updateDiceStats);
        refresh();
      }
    },50);
  }
}

/* ── Dice UI ── */
function renderDiceUI(game){return`
  <div class="dice-stage">
    <div class="dice-cube" id="dice-cube"><div class="dice-face" id="dice-face"><span id="dice-roll">50.00</span></div></div>
    <div id="dice-result" class="text-sm font-semibold" style="min-height:1.5rem;color:var(--text-muted)">Ready to roll</div>
    <div id="dice-multiplier" style="min-height:3rem"></div>
  </div>
  <div class="dice-slider-container">
    <div class="dice-slider-track">
      <div class="dice-slider-zone dice-slider-zone-green absolute top-0 bottom-0" id="dice-zone-green"></div>
      <div class="dice-slider-zone dice-slider-zone-red absolute top-0 bottom-0" id="dice-zone-red"></div>
    </div>
    <div class="dice-slider-marker" id="dice-marker" style="left:50%"></div>
  </div>
  <div class="dice-tick-labels"><span>0</span><span>25</span><span>50</span><span>75</span><span>100</span></div>
  <div class="dice-stats-row">
    <div class="dice-stat"><div class="dice-stat-label">Win Chance</div><div class="dice-stat-value" id="dice-win-chance">--</div></div>
    <div class="dice-stat"><div class="dice-stat-label">Multiplier</div><div class="dice-stat-value gold-text" id="dice-mult-preview">--</div></div>
    <div class="dice-stat"><div class="dice-stat-label">On Win</div><div class="dice-stat-value" id="dice-profit-preview">--</div></div>
  </div>
  <div class="grid grid-cols-2 gap-4 mb-4">
    <div><label class="label">Target (1-99)</label><input id="dice-target" type="number" value="50" min="1" max="99" class="input"></div>
    <div><label class="label">Condition</label><select id="dice-condition" class="select"><option value="under">Under</option><option value="over">Over</option></select></div>
  </div>
  <div class="mb-4">
    <label class="label">Bet Amount ($)</label>
    <input id="dice-amount" type="number" value="10.00" min="0.10" max="5000" step="1.00" class="input">
    <div class="flex gap-2 mt-2 flex-wrap">
      <button onclick="setBetAmount('dice-amount',5)" class="bet-chip">$5</button>
      <button onclick="setBetAmount('dice-amount',25)" class="bet-chip">$25</button>
      <button onclick="setBetAmount('dice-amount',100)" class="bet-chip">$100</button>
      <button onclick="setBetAmount('dice-amount',500)" class="bet-chip">$500</button>
      <button onclick="scaleBetAmount('dice-amount',0.5)" class="bet-chip">½</button>
      <button onclick="scaleBetAmount('dice-amount',2)" class="bet-chip">2×</button>
    </div>
  </div>
  <div class="mb-4"><label class="label">Client Seed</label><input id="dice-seed" type="text" value="${randomSeed()}" class="input font-mono text-xs"></div>
  <button onclick="placeDiceBet(${game.id})" id="dice-btn" class="btn btn-gold w-full py-3">Roll Dice</button>
  <div id="dice-proof" class="hidden mt-4"></div>`}

function updateDiceSliderZones(){
  const t=document.getElementById('dice-target'),c=document.getElementById('dice-condition'),g=document.getElementById('dice-zone-green'),r=document.getElementById('dice-zone-red');
  if(!t||!c||!g||!r)return;const val=parseInt(t.value)||50;const cond=c.value;
  if(cond==='under'){g.style.left='0%';g.style.width=`${val}%`;r.style.left=`${val}%`;r.style.width=`${100-val}%`}
  else{r.style.left='0%';r.style.width=`${val}%`;g.style.left=`${val}%`;g.style.width=`${100-val}%`}
}

function updateDiceStats(){
  const target=Math.max(1,Math.min(99,parseInt(document.getElementById('dice-target')?.value)||50));
  const condition=document.getElementById('dice-condition')?.value||'under';
  const amount=parseFloat(document.getElementById('dice-amount')?.value)||0;
  const rtp=window.currentGameRtp||95;
  let winChance,multiplier;
  if(condition==='under'){winChance=target*(rtp/100);multiplier=100/target*(rtp/100)}
  else{winChance=(100-target)*(rtp/100);multiplier=100/(100-target)*(rtp/100)}
  const onWin=amount*multiplier;
  const wc=document.getElementById('dice-win-chance'),mp=document.getElementById('dice-mult-preview'),pp=document.getElementById('dice-profit-preview');
  if(wc) wc.textContent=`${winChance.toFixed(2)}%`;
  if(mp) mp.textContent=`${multiplier.toFixed(4)}×`;
  if(pp) pp.textContent=`+$${onWin.toFixed(2)}`;
}

function setBetAmount(id,amount){const el=document.getElementById(id);if(!el)return;el.value=amount.toFixed(2);if(id==='dice-amount')updateDiceStats()}
function scaleBetAmount(id,factor){const el=document.getElementById(id);if(!el)return;const v=parseFloat(el.value)||1;el.value=Math.max(0.1,v*factor).toFixed(2);if(id==='dice-amount')updateDiceStats()}

function randomSeed(){return Math.random().toString(36).substring(2,10)}

/* ── Dice Bet Logic ── */
async function placeDiceBet(gameId){
  const btn=document.getElementById('dice-btn');
  const amount=document.getElementById('dice-amount').value;
  const target=parseInt(document.getElementById('dice-target').value);
  const condition=document.getElementById('dice-condition').value;
  const clientSeed=document.getElementById('dice-seed').value;

  btn.disabled=true;btn.textContent='Rolling...';
  const cube=document.getElementById('dice-cube');
  const rollDisplay=document.getElementById('dice-roll');
  const resultEl=document.getElementById('dice-result');
  const multEl=document.getElementById('dice-multiplier');
  cube.classList.remove('win','loss');cube.classList.add('rolling');
  resultEl.textContent='Rolling...';resultEl.style.color='var(--text-muted)';
  multEl.innerHTML='';
  document.getElementById('dice-proof').classList.add('hidden');

  const rollInterval=setInterval(()=>{rollDisplay.textContent=(Math.random()*100).toFixed(2)},60);

  const data=await api(`/games/${gameId}/bet`,{method:'POST',body:JSON.stringify({bet_amount:amount,client_seed:clientSeed,payload:{target,condition}})});

  clearInterval(rollInterval);
  cube.classList.remove('rolling');
  btn.disabled=false;btn.textContent='Roll Dice';

  if(!data||data._status){
    resultEl.textContent='Bet failed';resultEl.style.color='#f87171';
    rollDisplay.textContent='--';
    return showToast(data?.message||'Bet failed.','error');
  }
  const roll=data.outcome?.state?.roll,isWin=data.outcome?.state?.is_win;
  rollDisplay.textContent=roll!==undefined?roll.toFixed(2):'--';
  cube.classList.add(isWin?'win':'loss');
  setTimeout(()=>cube.classList.remove('win','loss'),2200);

  document.getElementById('dice-marker').style.left=`${roll}%`;
  resultEl.textContent=isWin?'Win!':'Lost';
  resultEl.style.color=isWin?'#4ade80':'#f87171';

  if(isWin){
    multEl.innerHTML=`<div class="multiplier-pop">+$${parseFloat(data.bet.payout_amount).toFixed(2)}</div>`;
    spawnWinBurst(cube.parentElement);
  }else{
    multEl.innerHTML=`<div class="text-lg text-red-400 font-bold" style="margin-top:.5rem">-$${parseFloat(amount).toFixed(2)}</div>`;
  }

  if(data.bet.result?.server_seed){
    const p=document.getElementById('dice-proof');p.classList.remove('hidden');
    p.innerHTML=`<div class="p-3 rounded-lg text-xs font-mono break-all" style="background:var(--surface-1);border:1px solid var(--border-subtle);color:var(--text-muted);line-height:1.6">Server: <span style="color:var(--text-primary)">${data.bet.result.server_seed}</span><br>Client: <span style="color:var(--text-primary)">${data.bet.client_seed}</span><br>Hash: <span style="color:var(--text-primary)">${data.bet.server_seed_hash}</span></div>`;
  }
  addBetToHistory(data.bet,isWin,amount,`Roll: ${roll?.toFixed(2)}`);
  const w=await api('/wallet');if(w)document.getElementById('game-balance-display').textContent=`$${parseFloat(w.balance).toFixed(2)}`;
}

/* ── Spin-to-Win UI (legacy) ── */
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
      addBetToHistory(data.bet,isWin,amount,`Sector ${landed}`);
      api('/wallet').then(w=>{if(w)document.getElementById('game-balance-display').textContent=`$${parseFloat(w.balance).toFixed(2)}`})
    },4500)
  },100)
}

/* ── Slots UI ── */
function renderSlotsUI(game){
  const paytable=SLOT_SYMBOL_KEYS.map(k=>{
    const s=SLOT_SYMBOLS[k];
    return `<div class="paytable-cell" title="${s.label}">
      <div class="paytable-symbol">${s.icon}</div>
      <div class="paytable-payout">${s.payout}×</div>
    </div>`;
  }).join('');
  const placeholderStrip=`<div class="reel-symbol">🎰</div>`;
  return `
    <div class="slot-machine">
      <div class="slot-title">Royal Slots</div>
      <div class="slot-window" id="slot-window">
        <div class="slot-reel" id="slot-reel-1"><div class="reel-strip" id="reel-strip-1">${placeholderStrip}</div></div>
        <div class="slot-reel" id="slot-reel-2"><div class="reel-strip" id="reel-strip-2">${placeholderStrip}</div></div>
        <div class="slot-reel" id="slot-reel-3"><div class="reel-strip" id="reel-strip-3">${placeholderStrip}</div></div>
        <div class="payline"></div>
      </div>
      <div class="text-center mt-4">
        <div id="slot-message" class="text-sm font-semibold" style="min-height:1.5rem;color:var(--text-muted)">Pull the lever</div>
        <div id="slot-multiplier" style="min-height:3rem"></div>
      </div>
    </div>
    <div class="grid grid-cols-2 gap-4 mb-4 mt-3">
      <div>
        <label class="label">Bet Amount ($)</label>
        <input id="slot-amount" type="number" value="10.00" min="0.10" max="5000" step="1.00" class="input">
        <div class="flex gap-2 mt-2 flex-wrap">
          <button onclick="setBetAmount('slot-amount',5)" class="bet-chip">$5</button>
          <button onclick="setBetAmount('slot-amount',25)" class="bet-chip">$25</button>
          <button onclick="setBetAmount('slot-amount',100)" class="bet-chip">$100</button>
          <button onclick="scaleBetAmount('slot-amount',0.5)" class="bet-chip">½</button>
          <button onclick="scaleBetAmount('slot-amount',2)" class="bet-chip">2×</button>
        </div>
      </div>
      <div>
        <label class="label">Client Seed</label>
        <input id="slot-seed" type="text" value="${randomSeed()}" class="input font-mono text-xs">
      </div>
    </div>
    <button onclick="placeSlotBet(${game.id})" id="slot-btn" class="btn btn-gold w-full py-3">Spin Reels</button>
    <div class="mt-4">
      <div class="text-xs font-semibold mb-2" style="color:var(--text-muted);letter-spacing:.06em;text-transform:uppercase">Three of a kind pays</div>
      <div class="paytable">${paytable}</div>
    </div>
    <div id="slot-proof" class="hidden mt-4"></div>
  `;
}

function buildReelStrip(targetSymbolKey){
  const symbols=[];
  for(let i=0;i<SLOT_REEL_LENGTH-1;i++){
    symbols.push(SLOT_SYMBOL_KEYS[Math.floor(Math.random()*SLOT_SYMBOL_KEYS.length)]);
  }
  symbols.push(targetSymbolKey);
  return symbols.map(k=>`<div class="reel-symbol">${SLOT_SYMBOLS[k].icon}</div>`).join('');
}

async function placeSlotBet(gameId){
  const btn=document.getElementById('slot-btn');
  const amount=document.getElementById('slot-amount').value;
  const clientSeed=document.getElementById('slot-seed').value;

  btn.disabled=true;btn.textContent='Spinning...';
  for(let i=1;i<=3;i++) document.getElementById(`slot-reel-${i}`).classList.remove('winning');
  const msg=document.getElementById('slot-message');const mult=document.getElementById('slot-multiplier');
  msg.textContent='Spinning...';msg.style.color='var(--text-muted)';
  mult.innerHTML='';
  document.getElementById('slot-proof').classList.add('hidden');

  // Pre-fill each strip with random symbols and start the spin animation.
  for(let i=1;i<=3;i++){
    const strip=document.getElementById(`reel-strip-${i}`);
    strip.style.transition='none';
    strip.style.transform='translateY(0px)';
    // Use a long random fill so the spin looks busy until results land.
    strip.innerHTML=Array.from({length:SLOT_REEL_LENGTH},()=>{
      const k=SLOT_SYMBOL_KEYS[Math.floor(Math.random()*SLOT_SYMBOL_KEYS.length)];
      return `<div class="reel-symbol">${SLOT_SYMBOLS[k].icon}</div>`;
    }).join('');
  }
  await new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)));

  const data=await api(`/games/${gameId}/bet`,{method:'POST',body:JSON.stringify({bet_amount:amount,client_seed:clientSeed,payload:{}})});
  if(!data||data._status){
    btn.disabled=false;btn.textContent='Spin Reels';
    msg.textContent='Spin failed';msg.style.color='#f87171';
    return showToast(data?.message||'Spin failed.','error');
  }

  const reels=data.outcome?.state?.reels||['cherry','lemon','bell'];
  const matchType=data.outcome?.state?.match_type;
  const isWin=data.outcome?.state?.is_win;

  // Rebuild each strip with the result symbol at the final visible slot.
  // Stagger the stop so reels land one-by-one.
  const stopDurations=[1.4,1.9,2.5];
  for(let i=0;i<3;i++){
    const strip=document.getElementById(`reel-strip-${i+1}`);
    strip.innerHTML=buildReelStrip(reels[i]);
    strip.style.transition='none';
    strip.style.transform='translateY(0px)';
    // Force reflow so the next transform animates.
    void strip.offsetHeight;
    strip.style.transition=`transform ${stopDurations[i]}s cubic-bezier(0.18,0.8,0.18,1)`;
    strip.style.transform=`translateY(-${(SLOT_REEL_LENGTH-1)*SLOT_SYMBOL_HEIGHT}px)`;
  }

  const totalMs=stopDurations[2]*1000+120;
  setTimeout(()=>{
    btn.disabled=false;btn.textContent='Spin Reels';
    if(matchType==='three_of_a_kind'){
      for(let i=1;i<=3;i++) document.getElementById(`slot-reel-${i}`).classList.add('winning');
      msg.textContent=`Three of a kind — ${SLOT_SYMBOLS[reels[0]].label}!`;
      msg.style.color='#4ade80';
      mult.innerHTML=`<div class="multiplier-pop">+$${parseFloat(data.bet.payout_amount).toFixed(2)}</div>`;
      spawnWinBurst(document.getElementById('slot-window'));
    } else if(matchType==='cherry_consolation'){
      msg.textContent='Cherry consolation';
      msg.style.color='#facc15';
      mult.innerHTML=`<div class="text-lg gold-text font-bold" style="margin-top:.5rem">+$${parseFloat(data.bet.payout_amount).toFixed(2)}</div>`;
    } else {
      msg.textContent='No match';
      msg.style.color='#f87171';
      mult.innerHTML=`<div class="text-lg text-red-400 font-bold" style="margin-top:.5rem">-$${parseFloat(amount).toFixed(2)}</div>`;
    }

    if(data.bet.result?.server_seed){
      const p=document.getElementById('slot-proof');p.classList.remove('hidden');
      p.innerHTML=`<div class="p-3 rounded-lg text-xs font-mono break-all" style="background:var(--surface-1);border:1px solid var(--border-subtle);color:var(--text-muted);line-height:1.6">Server: <span style="color:var(--text-primary)">${data.bet.result.server_seed}</span><br>Client: <span style="color:var(--text-primary)">${data.bet.client_seed}</span><br>Hash: <span style="color:var(--text-primary)">${data.bet.server_seed_hash}</span></div>`;
    }
    addBetToHistory(data.bet,isWin,amount,reels.map(r=>SLOT_SYMBOLS[r].icon).join(' '));
    api('/wallet').then(w=>{if(w)document.getElementById('game-balance-display').textContent=`$${parseFloat(w.balance).toFixed(2)}`});
  },totalMs);
}

/* ── Shared FX helpers ── */
function addBetToHistory(bet,isWin,betAmount,summary){
  const h=document.getElementById('bet-history-list');if(!h)return;
  const e=document.createElement('div');
  e.className=`p-3 rounded flex items-center justify-between border-l-4 ${isWin?'border-green-500 text-green-400':'border-red-500 text-red-400'}`;
  e.style.background='var(--surface-1)';
  e.innerHTML=`<span>#${bet.id} ${summary}</span><span>${isWin?'+$'+parseFloat(bet.payout_amount).toFixed(2):'-$'+parseFloat(betAmount).toFixed(2)}</span>`;
  h.prepend(e);if(h.children.length>12)h.removeChild(h.lastChild);
}

function spawnWinBurst(container){
  if(!container)return;
  const burst=document.createElement('div');burst.className='win-burst';
  const count=24;
  for(let i=0;i<count;i++){
    const spark=document.createElement('div');spark.className='spark';
    const angle=(i/count)*2*Math.PI+Math.random()*0.4;
    const dist=80+Math.random()*120;
    spark.style.setProperty('--dx',`${Math.cos(angle)*dist}px`);
    spark.style.setProperty('--dy',`${Math.sin(angle)*dist}px`);
    spark.style.animationDelay=`${Math.random()*0.15}s`;
    burst.appendChild(spark);
  }
  // Anchor at center of container
  const prevPos=getComputedStyle(container).position;
  if(prevPos==='static') container.style.position='relative';
  container.appendChild(burst);
  setTimeout(()=>burst.remove(),1300);
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
  else if(tab==='risk')renderAdminRisk(content);else if(tab==='games')renderAdminGames(content)
}

async function renderAdminUsers(content){
  const data=await api('/admin/users');const users=data?.users||[];
  content.innerHTML=`<div class="table-wrapper"><table><thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Status</th><th>Joined</th><th>Actions</th></tr></thead><tbody>${users.map(u=>`<tr>
    <td class="font-mono text-xs">${u.id}</td>
    <td class="font-semibold" style="color:var(--text-primary)">${u.name}</td>
    <td class="font-mono text-xs">${u.email}</td>
    <td><span class="badge ${u.status==='active'?'badge-green':'badge-red'}">${u.status}</span></td>
    <td class="text-xs">${new Date(u.created_at).toLocaleDateString()}</td>
    <td class="flex gap-2">${u.status==='active'?`<button onclick="adminDisableUser(${u.id})" class="btn btn-ghost btn-sm">Disable</button>`:''}<button onclick="adminDeleteUser(${u.id})" class="btn btn-danger btn-sm">Delete</button></td>
  </tr>`).join('')}</tbody></table></div>`
}

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

/* ── Expose handlers for inline onclick attributes (module scope -> window) ── */
Object.assign(window,{
  handleLogin,handleRegister,handleLogout,navigate,toggleSidebar,
  handleDeposit,handleWithdraw,
  setBetAmount,scaleBetAmount,
  placeDiceBet,placeSpinBet,placeSlotBet,
  handleUpdateProfile,handleUpdatePassword,handleUpdateLimits,handleSelfExclude,
  switchAdminTab,adminDisableUser,adminDeleteUser,
  adminApproveWithdrawal,adminRejectWithdrawal,
  adminEditRtp,adminToggleStatus
});

/* ── Boot ── */
document.addEventListener('DOMContentLoaded',()=>{
  let hash=window.location.hash.slice(1)||(hasToken()?'dashboard':'login');let params={};
  if(hash.startsWith('game-play/')){params={gameId:hash.split('/')[1]};hash='game-play'}
  renderPage(hash,params)
})
