// Uses the installed Firefox with a unique headless profile and fixture data only.
// Protocol: https://firefox-source-docs.mozilla.org/remote/marionette/Protocol.html
const fs=require('fs'),path=require('path'),cp=require('child_process'),net=require('net'),url=require('url');
const root=path.resolve(__dirname,'..'),dir=path.join(root,'.codex-review/20261014-stabilization/viewport');
fs.mkdirSync(dir,{recursive:true});
const sessionDir=fs.mkdtempSync(path.join(dir,'sessions-')),profile=fs.mkdtempSync(path.join(dir,'firefox-'));
const php=process.env.PHP_BINARY || 'C:/xampp/php/php.exe';
const firefox=process.env.FIREFOX_BINARY || 'C:/Program Files/Mozilla Firefox/firefox.exe';
const scenarios=process.env.FPRS_VIEWPORT_SCENARIOS?.split(',') || ['admin_declined_catalog','admin_alignment_catalog','admin_pending_catalog','admin_parlor_catalog','director_pending_catalog','package_form','client_dashboard','director_outside_dashboard'];
const widths=[320,375,640,768,1024,1280,1536];
function fixture(name){
 const r=cp.spawnSync(php,['tests/endpoint_fixture.php',name,'render'],{cwd:root,encoding:'utf8',env:{...process.env,FPRS_TEST_SESSION_PATH:sessionDir}});
 if(r.status!==0)throw Error('Fixture failed '+name+' '+r.stderr);
 let html=JSON.parse(r.stdout).body;
 html=html.replace(/(?:src|href)="(\/fprs\/(?:bootstrap|assets|uploads)[^"]*)"/g,(match,value)=>{
  const relative=value.slice('/fprs/'.length).split('?')[0];
  return match.slice(0,match.indexOf('=')+1)+'"'+url.pathToFileURL(path.join(root,relative)).href+'"';
 });
 html=html.replace('</head>','<style>*,*::before,*::after{animation:none!important;transition:none!important}.sky-backdrop{display:none!important}</style></head>');
 const file=path.join(dir,name+'.html');fs.writeFileSync(file,html);return url.pathToFileURL(file).href;
}
const pages=Object.fromEntries(scenarios.map(s=>[s,fixture(s)]));
const sleep=ms=>new Promise(r=>setTimeout(r,ms));
let browser,socket,buffer=Buffer.alloc(0),id=0,pending=new Map(),handshakeResolve;
function consume(chunk){
 buffer=Buffer.concat([buffer,chunk]);
 while(true){
  const colon=buffer.indexOf(58);if(colon<0)return;
  const length=Number(buffer.subarray(0,colon).toString());
  if(!Number.isFinite(length)||length>20000000)throw Error('Bad Marionette frame');
  if(buffer.length<colon+1+length)return;
  const message=JSON.parse(buffer.subarray(colon+1,colon+1+length).toString());buffer=buffer.subarray(colon+1+length);
  if(!Array.isArray(message)){if(handshakeResolve)handshakeResolve(message);continue;}
  const request=pending.get(message[1]);if(!request)continue;pending.delete(message[1]);clearTimeout(request.timer);
  if(message[2])request.reject(Error(JSON.stringify(message[2])));else request.resolve(message[3]);
 }
}
function command(name,params={}){
 return new Promise((resolve,reject)=>{
  const messageId=++id,text=Buffer.from(JSON.stringify([0,messageId,name,params]));
  const timer=setTimeout(()=>{pending.delete(messageId);reject(Error('Timeout '+name));},30000);
  pending.set(messageId,{resolve,reject,timer});socket.write(Buffer.concat([Buffer.from(text.length+':'),text]));
 });
}
async function execute(script){
 const r=await command('WebDriver:ExecuteScript',{script,args:[],newSandbox:true,sandbox:'default'});
 return r && Object.prototype.hasOwnProperty.call(r,'value') ? r.value : r;
}
(async()=>{
 const allocator=net.createServer();await new Promise(r=>allocator.listen(0,'127.0.0.1',r));const port=allocator.address().port;await new Promise(r=>allocator.close(r));
 fs.writeFileSync(path.join(profile,'user.js'),'user_pref("marionette.port",'+port+');\nuser_pref("browser.shell.checkDefaultBrowser",false);\nuser_pref("browser.startup.homepage_override.mstone","ignore");\n');
 browser=cp.spawn(firefox,['--headless','--no-remote','--marionette','--profile',profile],{windowsHide:true,stdio:['ignore','pipe','pipe']});
 let launchLog='';browser.stdout.on('data',c=>{launchLog+=c.toString();});browser.stderr.on('data',c=>{launchLog+=c.toString();});
 browser.on('error',e=>{launchLog+=e.message;});
 let connected=false;
 for(let attempt=0;attempt<60&&!connected;attempt++){
  connected=await new Promise(resolve=>{const s=net.createConnection({host:'127.0.0.1',port});s.once('error',()=>{s.destroy();resolve(false);});s.once('connect',()=>{socket=s;resolve(true);});});
  if(!connected)await sleep(300);
 }
 if(!connected)throw Error('Firefox connection unavailable: '+launchLog.slice(-2000));
 const handshake=new Promise(resolve=>{handshakeResolve=resolve;});
 socket.on('data',consume);
 socket.on('error',e=>{for(const p of pending.values()){clearTimeout(p.timer);p.reject(e);}pending.clear();});
 await Promise.race([handshake,sleep(5000).then(()=>{throw Error('No Marionette handshake');})]);
 const session=await command('WebDriver:NewSession',{capabilities:{alwaysMatch:{acceptInsecureCerts:true}}});
 console.log('Firefox fixture viewport session started.');
 const results=[];
 for(const scenario of scenarios){
  for(const width of widths){
   await command('WebDriver:SwitchToFrame',{id:null});
   await command('WebDriver:SetWindowRect',{width:Math.max(450,width),height:1000});
   // Firefox desktop clamps windows below 450px. An iframe supplies the actual
   // requested CSS viewport, without changing the application's responsive CSS.
   const host=path.join(dir,scenario+'-'+width+'-host.html');
   fs.writeFileSync(host,'<!doctype html><html><head><meta charset="utf-8"><style>html,body{margin:0;padding:0}iframe{display:block;border:0}</style></head><body><iframe id="viewport" width="'+width+'" height="900" src="'+pages[scenario]+'"></iframe></body></html>');
   await command('WebDriver:Navigate',{url:url.pathToFileURL(host).href});
   await command('WebDriver:SwitchToFrame',{id:0});
   let styled=false;
   for(let i=0;i<30&&!styled;i++){
    styled=await execute("const p=document.createElement('div');p.className='hidden';document.body.appendChild(p);const ok=getComputedStyle(p).display==='none';p.remove();return ok;");
    if(!styled)await sleep(200);
   }
   if(!styled)throw Error('Tailwind did not load for '+scenario);
   const dimensions=await execute("return {width:innerWidth,scroll:Math.max(document.documentElement.scrollWidth,document.body.scrollWidth),headerBack:[...document.querySelectorAll('header a')].some(a=>a.textContent.trim()==='Back'),backLinks:[...document.querySelectorAll('main a')].filter(a=>a.textContent.trim().startsWith('Back')).map(a=>({label:a.textContent.trim(),href:a.getAttribute('href')})),overflow:[...document.querySelectorAll('main *')].filter(e=>e.getBoundingClientRect().right>innerWidth+1).slice(0,6).map(e=>({tag:e.tagName,classes:e.className}))};");
   const alignment=await execute("return [...document.querySelectorAll('.admin-record-grid')].every(row=>{const cells=[...row.children];if(cells.length!==3)return false;const r=cells.map(e=>e.getBoundingClientRect());const wide=innerWidth>=900;return cells.every(e=>e.scrollWidth<=e.clientWidth+1) && (wide ? r[0].right<=r[1].left+1 && r[1].right<=r[2].left+1 : r[0].bottom<=r[1].top+1 && r[1].bottom<=r[2].top+1) && [...row.querySelectorAll('.admin-record-actions a,.admin-record-actions button')].every(e=>e.getBoundingClientRect().height>=44);});");
   let questionnaire=null;
   if(scenario==='feedback_form') {
    questionnaire=await execute("const groups=[...document.querySelectorAll('fieldset[role=radiogroup]')];const form=document.querySelector('form');const output={groups:groups.length,options:groups.every(g=>g.querySelectorAll('input[type=radio]').length===5),initiallyIncomplete:!form.checkValidity(),touchTargets:groups.every(g=>[...g.querySelectorAll('label')].every(l=>l.getBoundingClientRect().height>=44))};groups.forEach(g=>g.querySelectorAll('input')[2].click());output.complete=form.checkValidity();output.selected=groups.every(g=>g.querySelectorAll('input')[2].closest('label').classList.contains('bg-funeral-50'));return output;");
   }
   let modal=null;
   if(scenario==='admin_declined_catalog'){
    modal=await execute("const b=document.querySelector('[data-delete-url]');b.click();const r=document.querySelector('#delete-modal>div').getBoundingClientRect();const out={visible:getComputedStyle(document.getElementById('delete-modal')).display!=='none',left:r.left,right:r.right,top:r.top,bottom:r.bottom};document.querySelector('#delete-modal button').click();out.closed=getComputedStyle(document.getElementById('delete-modal')).display==='none';return out;");
   }
   let cascade=null;
   if(scenario==='client_dashboard'){
    cascade=await execute("const m=document.getElementById('search-municipality');m.value='Guimbal';m.dispatchEvent(new Event('change'));return document.getElementById('search-barangay').options.length;");
   }
   const screenshot=await command('WebDriver:TakeScreenshot',{full:true});
   const base64=typeof screenshot==='string'?screenshot:screenshot.value;
   fs.writeFileSync(path.join(dir,scenario+'-'+width+'.png'),Buffer.from(base64,'base64'));
   const passed=(!questionnaire||(questionnaire.groups===22&&questionnaire.options&&questionnaire.initiallyIncomplete&&questionnaire.touchTargets&&questionnaire.complete&&questionnaire.selected)) && alignment && dimensions.width===width && dimensions.scroll<=width+1 && !dimensions.headerBack && dimensions.backLinks.length>0 && (!modal||(modal.visible&&modal.closed&&modal.left>=0&&modal.right<=width&&modal.top>=0&&modal.bottom<=900)) && (cascade===null||cascade===34);
   results.push({scenario,requestedWidth:width,...dimensions,alignment,questionnaire,modal,cascade,passed});
   console.log(scenario+' '+width+'px: '+(passed?'PASS':'FAIL')+' (actual '+dimensions.width+', scroll '+dimensions.scroll+')');
  }
 }
 const report={browser:session.capabilities?.browserVersion||session.value?.capabilities?.browserVersion||'Firefox',fixtureOnly:true,cssViewportViaIframe:true,results};
 fs.writeFileSync(path.join(dir,'results.json'),JSON.stringify(report,null,2));
 if(results.some(r=>!r.passed))process.exitCode=1;
 console.log('Viewport results: '+results.filter(r=>r.passed).length+'/'+results.length+' passed.');
})().catch(e=>{console.error(e.stack);process.exitCode=1;}).finally(async()=>{
 if(socket){try{await command('Marionette:Quit',{flags:['eForceQuit']});}catch{}socket.destroy();}
 if(browser&&!browser.killed)browser.kill();
 for(const name of fs.readdirSync(sessionDir))fs.unlinkSync(path.join(sessionDir,name));fs.rmdirSync(sessionDir);
});
