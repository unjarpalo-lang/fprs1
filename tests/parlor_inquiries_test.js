// Isolated route tests: three inquiries for one parlor, one for another.
const fs=require('fs'),os=require('os'),path=require('path'),cp=require('child_process'),assert=require('assert/strict');
const sessions=fs.mkdtempSync(path.join(os.tmpdir(),'fprs-inquiry-scope-'));
let count=0;
function route(overrides={},render=false){
 const config={route:'director/inquiries.php',user:2,method:'GET',inquiryScope:true,...overrides};
 const r=cp.spawnSync('C:/xampp/php/php.exe',['tests/endpoint_fixture.php','selected_fix',...(render?['render']:[])],{cwd:path.resolve(__dirname,'..'),encoding:'utf8',env:{...process.env,FPRS_TEST_SESSION_PATH:sessions,FPRS_FIX_CASE:JSON.stringify(config)}});
 assert.equal(r.status,0,r.stderr);assert.equal(r.stderr,'');count++;return JSON.parse(r.stdout);
}
try{
 const first=route({selectedParlor:10});assert.equal(first.status,200);for(const id of [1,2,3])assert.match(first.body,new RegExp('Scope inquiry '+id));assert.doesNotMatch(first.body,/Scope inquiry 4/);
 const second=route({selectedParlor:11});assert.equal(second.status,200);assert.match(second.body,/Scope inquiry 4/);assert.doesNotMatch(second.body,/Scope inquiry [123]/);
 for(const selectedParlor of [0,12,999]){const r=route({selectedParlor});assert.equal(r.status,302);assert.doesNotMatch(r.body,/Scope inquiry/);}
 for(const action of ['confirm','avail','cancel']){const r=route({selectedParlor:10,method:'POST',post:{inquiry_id:'4',action}});assert.equal(r.status,409);assert.equal(r.inquiries.find(x=>x.id===4).status,'inquired');}
 const own=route({selectedParlor:11,method:'POST',post:{inquiry_id:'4',action:'confirm'}});assert.equal(own.status,302);assert.equal(own.inquiries.find(x=>x.id===4).status,'confirmed');for(const id of [1,2,3])assert.equal(own.inquiries.find(x=>x.id===id).status,'inquired');
 const noToken=route({selectedParlor:10,method:'POST',token:false,post:{inquiry_id:'1',action:'confirm'}});assert.equal(noToken.status,403);assert.equal(noToken.inquiries[0].status,'inquired');
 for(const selectedParlor of [0,12,999]){const r=route({route:'director/dashboard.php',selectedParlor},true);assert.doesNotMatch(r.body,/href="\/fprs\/director\/inquiries.php"/);}
 const selected=route({route:'director/dashboard.php',selectedParlor:10},true);assert.equal((selected.body.match(/href="\/fprs\/director\/inquiries.php"/g)||[]).length,1);assert.doesNotMatch(selected.body.match(/<header\b[\s\S]*?<\/header>/)?.[0] || '',/director\/inquiries.php/);
 console.log('PASS: '+count+' parlor-specific listing, action, access and navigation scenarios.');
}finally{for(const f of fs.readdirSync(sessions))fs.unlinkSync(path.join(sessions,f));fs.rmdirSync(sessions);}
