// Isolated SQLite route checks. No application database records are written.
const fs=require('fs'),os=require('os'),path=require('path'),cp=require('child_process'),assert=require('assert/strict');
const sessions=fs.mkdtempSync(path.join(os.tmpdir(),'fprs-ui-corrections-'));let count=0;
function request(config,render=false){const r=cp.spawnSync('C:/xampp/php/php.exe',['tests/endpoint_fixture.php','selected_fix',...(render?['render']:[])],{cwd:path.resolve(__dirname,'..'),encoding:'utf8',env:{...process.env,FPRS_TEST_SESSION_PATH:sessions,FPRS_FIX_CASE:JSON.stringify({method:'GET',...config})}});assert.equal(r.status,0,r.stderr);assert.equal(r.stderr,'');count++;return JSON.parse(r.stdout);}
function backDestination(body){return body.match(/<a href="([^"]+)" class="mb-4 /)?.[1];}
function cancelDestination(body){return body.match(/<a href="([^"]+)"[^>]*>Cancel<\/a>/)?.[1];}
try{
 for(const selectedParlor of [0,10,11]){
  const overview=request({route:'director/dashboard.php',selectedParlor,get:{overview:'1'}});assert.equal(overview.status,200);assert.match(overview.body,/Welcome,|Manage Parlors/);assert.doesNotMatch(overview.body,/Selected parlor|Manage Packages/);assert.equal(overview.selected_parlor_id,selectedParlor);assert.equal(backDestination(overview.body),'/fprs/');
 }
 const selectFromOverview=request({route:'director/dashboard.php',selectedParlor:10,get:{overview:'1'},method:'POST',post:{select_parlor:'1',parlor_id:'11'}});assert.equal(selectFromOverview.status,302);assert.equal(selectFromOverview.selected_parlor_id,11);
 const selectedDashboard=request({route:'director/dashboard.php',selectedParlor:11});assert.match(selectedDashboard.body,/Selected parlor/);assert.match(selectedDashboard.body,/Manage Packages/);
 for(const selectedParlor of [0,10]){
  const account=request({route:'director/account.php',selectedParlor});assert.equal(backDestination(account.body),'/fprs/director/funeral_parlors.php');assert.equal(cancelDestination(account.body),backDestination(account.body));
  const list=request({route:'director/funeral_parlors.php',selectedParlor});assert.equal(backDestination(list.body),'/fprs/director/dashboard.php?overview=1');
 }
 for(const config of [{get:{id:'10'},selectedParlor:10},{get:{id:'11'},selectedParlor:11},{}]){
  const form=request({route:'director/funeral_register.php',...config});assert.equal(form.status,200);assert.equal(backDestination(form.body),config.get?'/fprs/director/dashboard.php':'/fprs/director/funeral_parlors.php');assert.equal(cancelDestination(form.body),backDestination(form.body));
 }
 const afterPersonalSave=request({route:'director/account.php',method:'POST',post:{update_account:'1',full_name:'Updated Director',phone:'09000000000'}});assert.equal(backDestination(afterPersonalSave.body),'/fprs/director/funeral_parlors.php');
 const personal=request({route:'director/account.php',selectedParlor:0});assert.equal(personal.status,200);assert.match(personal.body,/name="full_name"/);assert.doesNotMatch(personal.body,/name="(?:municipality|barangay|license|parlor_contact_number)"/);
 const saved=request({route:'director/account.php',selectedParlor:0,method:'POST',post:{update_account:'1',full_name:'Updated Director',phone:'09000000000'}});assert.equal(saved.users.find(x=>x.id===2).full_name,'Updated Director');assert.equal(saved.parlors.find(x=>x.id===10).name,'Scoped');
 const legacy=request({route:'director/account.php',method:'POST',post:{update_location:'1',municipality:'Guimbal',barangay:'Baras',parlor_contact_number:'111'}});assert.equal(legacy.status,400);assert.equal(legacy.parlors.find(x=>x.id===10).location,'Bacong, Guimbal');
 for(const selectedParlor of [0,10]){const r=request({route:'director/dashboard.php',selectedParlor},true);assert.doesNotMatch(r.body,/Personal Account/);if(selectedParlor)assert.match(r.body,/funeral_register.php\?id=10/);}
 for(const selectedParlor of [0,10]){const r=request({route:'director/funeral_parlors.php',selectedParlor},true);const header=r.body.match(/<header\b[\s\S]*?<\/header>/)?.[0]||'';assert.equal((header.match(/href="\/fprs\/director\/account.php"/g)||[]).length,2);assert.doesNotMatch(r.body.slice(r.body.indexOf('<main')),/href="\/fprs\/director\/account.php"/);}
 for(const route of ['director/account.php','director/packages.php','director/funeral_register.php']){const r=request({route},true);assert.doesNotMatch(r.body.match(/<header\b[\s\S]*?<\/header>/)?.[0]||'',/href="\/fprs\/director\/account.php"/);}
 const form=request({route:'director/funeral_register.php',get:{id:'10'},selectedParlor:10});assert.equal(form.status,200);assert.match(form.body,/name="license"/);assert.match(form.body,/Maximum 40 MB/);assert.doesNotMatch(form.body,/name="full_name"|Submit each parlor separately/);assert.match(form.body,/Save Account/);
 const fields={id:'10',name:'Updated Parlor',municipality:'Guimbal',barangay:'Baras',contact_number:'09000000001'};
 const parlor=request({route:'director/funeral_register.php',get:{id:'10'},method:'POST',post:fields});assert.equal(parlor.parlors.find(x=>x.id===10).name,'Updated Parlor');assert.equal(parlor.parlors.find(x=>x.id===10).location,'Baras, Guimbal');assert.equal(parlor.parlors.find(x=>x.id===11).name,'Outside');assert.equal(parlor.users.find(x=>x.id===2).full_name,'Director');assert.equal(parlor.parlors.find(x=>x.id===10).status,'pending');
 const notSelected=request({route:'director/funeral_register.php',method:'POST',selectedParlor:11,post:fields});assert.equal(notSelected.status,403);assert.equal(notSelected.parlors.find(x=>x.id===10).name,'Scoped');
 const foreign=request({route:'director/funeral_register.php',get:{id:'12'}});assert.equal(foreign.status,404);
 const noSelection=request({route:'director/funeral_register.php',get:{id:'10'},selectedParlor:0});assert.equal(noSelection.status,302);
 const mismatched=request({route:'director/funeral_register.php',get:{id:'11'},method:'POST',post:fields});assert.equal(mismatched.status,400);
 const missingToken=request({route:'director/funeral_register.php',method:'POST',token:false,post:fields});assert.equal(missingToken.status,403);
 for(const file of [{error:0,size:40*1024*1024+1},{error:1},{error:2}]){const rejected=request({route:'director/funeral_register.php',method:'POST',post:fields,files:{license:file}});assert.equal(rejected.status,413);assert.match(rejected.body,/40 MB or smaller/);assert.equal(rejected.parlors.find(x=>x.id===10).name,'Scoped');}
 const packages=request({route:'director/packages.php'});assert.match(packages.body,/package-catalog-grid/);assert.match(packages.body,/package-inclusions-compact/);assert.doesNotMatch(packages.body,/package-inclusions-compact[^>]*sm:grid-cols-2/);
 const login=request({route:'login.php'});assert.match(login.body,/id="login-password-toggle" type="button"/);assert.match(login.body,/name="password" type="password"/);assert.match(login.body,/aria-pressed="false"/);
 console.log('PASS: '+count+' account separation, selected-parlor updates, upload rejection and UI route checks.');
}finally{for(const f of fs.readdirSync(sessions))fs.unlinkSync(path.join(sessions,f));fs.rmdirSync(sessions);}
