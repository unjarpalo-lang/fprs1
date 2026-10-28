// Approval transitions through actual route code, using isolated SQLite records.
const fs=require('fs'),os=require('os'),path=require('path'),cp=require('child_process'),assert=require('assert/strict');
const sessions=fs.mkdtempSync(path.join(os.tmpdir(),'fprs-parlor-review-'));let count=0;
const fields={id:'10',name:'Scoped',municipality:'Guimbal',barangay:'Bacong',contact_number:''};
// The fixture's contact is empty; a real submitted contact must be nonempty.
// Seed the original contact through fixture setup for true no-change tests.
fields.contact_number='09000000000';
function request(options={}){const r=cp.spawnSync('C:/xampp/php/php.exe',['tests/endpoint_fixture.php','selected_fix'],{cwd:path.resolve(__dirname,'..'),encoding:'utf8',env:{...process.env,FPRS_TEST_SESSION_PATH:sessions,FPRS_FIX_CASE:JSON.stringify({route:'director/funeral_register.php',method:'POST',user:2,selectedParlor:10,parlorStatus:'approved',originalParlorContact:fields.contact_number,post:fields,...options})}});assert.equal(r.status,0,r.stderr);assert.equal(r.stderr,'');count++;return JSON.parse(r.stdout);}
try{
 for(const post of [{...fields,name:'Renamed parlor'},{...fields,barangay:'Baras'},{...fields,contact_number:'09000000001'},{...fields,name:'Renamed',barangay:'Baras',contact_number:'09000000001'}]){
  const r=request({post});assert.equal(r.status,200);assert.equal(r.parlors.find(x=>x.id===10).status,'pending');assert.ok(!r.client_visible_package_ids.includes(100));assert.equal(r.parlors.find(x=>x.id===12).status,'approved');assert.ok(r.client_visible_package_ids.includes(102));assert.match(r.body,/Changes submitted for admin approval/);
 }
 for(const status of ['approved','pending','rejected']){const r=request({parlorStatus:status});assert.equal(r.parlors.find(x=>x.id===10).status,status);assert.equal(r.client_visible_package_ids.includes(100),status==='approved');assert.match(r.body,/No changes to save/);}
 const sample=path.resolve(__dirname,'../uploads/sample-licenses/UC_Ddash.jpg');
 const license=request({mockLicenseUpload:true,files:{license:{name:'replacement.jpg',tmp_name:sample,size:fs.statSync(sample).size,error:0,type:'image/jpeg'}}});assert.equal(license.parlors.find(x=>x.id===10).status,'pending');assert.match(license.parlors.find(x=>x.id===10).license_path,/^uploads\/parlor_license_/);assert.ok(!license.client_visible_package_ids.includes(100));
 const invalid=request({post:{...fields,name:'X'.repeat(201)}});assert.equal(invalid.status,422);assert.equal(invalid.parlors.find(x=>x.id===10).status,'approved');assert.equal(invalid.parlors.find(x=>x.id===10).name,'Scoped');
 const oversized=request({files:{license:{error:0,size:40*1024*1024+1}}});assert.equal(oversized.status,413);assert.equal(oversized.parlors.find(x=>x.id===10).status,'approved');
 const badToken=request({token:false,post:{...fields,name:'Unauthorized'}});assert.equal(badToken.status,403);assert.equal(badToken.parlors.find(x=>x.id===10).status,'approved');
 const personal=request({route:'director/account.php',post:{update_account:'1',full_name:'Updated director',phone:'09000000002'}});assert.equal(personal.parlors.find(x=>x.id===10).status,'approved');
 const approved=request({post:{...fields,name:'Reviewed name'},thenAdminApprove:true});assert.equal(approved.before_admin_status,'pending');assert.equal(approved.parlors.find(x=>x.id===10).status,'approved');assert.ok(approved.client_visible_package_ids.includes(100));
 const outside=request({post:{...fields,municipality:'Oton',barangay:'Batuan Ilaya'},thenAdminApprove:true});assert.equal(outside.before_admin_status,'pending');assert.equal(outside.parlors.find(x=>x.id===10).status,'pending');assert.ok(!outside.client_visible_package_ids.includes(100));
 console.log('PASS: '+count+' parlor review, no-change, license replacement, visibility, validation and admin reapproval scenarios.');
}finally{for(const f of fs.readdirSync(sessions))fs.unlinkSync(path.join(sessions,f));fs.rmdirSync(sessions);}
