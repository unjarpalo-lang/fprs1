// Real route code with isolated SQLite records; never touches the live database.
const fs=require('fs'),path=require('path'),os=require('os'),cp=require('child_process'),assert=require('assert/strict');
const dir=fs.mkdtempSync(path.join(os.tmpdir(),'fprs-selected-fixes-'));
let count=0;
function route(config){
 const r=cp.spawnSync('C:/xampp/php/php.exe',['tests/endpoint_fixture.php','selected_fix'],{cwd:path.join(__dirname,'..'),encoding:'utf8',env:{...process.env,FPRS_TEST_SESSION_PATH:dir,FPRS_FIX_CASE:JSON.stringify(config)}});
 assert.equal(r.status,0,r.stderr);const data=JSON.parse(r.stdout);assert.doesNotMatch(r.stderr,/Fatal error|Warning|Deprecated/);count++;return data;
}
const pkg={route:'director/package_action.php',post:{id:'100',title:'Valid title',price:'123.45','service_names':['Casket'],'service_details':['Wood']}};
const stock={route:'director/inventory_action.php',post:{id:'100',name:'Valid resource',quantity:'5',item_type:'flower'}};
try{
 for(const price of ['not-a-number',[],['1'],'-1','100000000','1.001','INF','1e99','']){
  const r=route({...pkg,post:{...pkg.post,price}});assert.equal(r.status,422);assert.equal(r.packages.find(x=>x.id===100).title,'Original');assert.equal(r.prices.find(x=>x.id===100).price,100);
 }
 const invalidWithImage=route({...pkg,files:{image:{error:0}},post:{...pkg.post,price:'invalid'}});assert.equal(invalidWithImage.status,422);assert.match(invalidWithImage.body,/Price must be/);
 for(const price of ['0','123.45','99999999.99']){const r=route({...pkg,post:{...pkg.post,price}});assert.equal(r.status,302);assert.equal(r.prices.find(x=>x.id===100).price,Number(price));assert.equal(r.packages.find(x=>x.id===100).title,'Valid title');}
 for(const quantity of ['-5','1.5','text',[],['1'],'2147483648','1e3','']){const r=route({...stock,post:{...stock.post,quantity}});assert.equal(r.status,422);assert.equal(r.resources.find(x=>x.id===100).quantity,5);assert.equal(r.resources.find(x=>x.id===100).name,'Original resource');}
 for(const quantity of ['0','5','2147483647']){const r=route({...stock,post:{...stock.post,quantity}});assert.equal(r.resources.find(x=>x.id===100).quantity,Number(quantity));assert.equal(r.resources.find(x=>x.id===100).available_status,quantity==='0'?'missing':'available');}
 for(const base of [pkg,stock,{route:'director/account.php',post:{update_account:'1',full_name:'Changed',phone:'1'}},{route:'director/funeral_register.php',post:{name:'Changed'}},{route:'director/dashboard.php',post:{select_parlor:'1',parlor_id:'10'}},{route:'admin/shop_action.php',user:1,post:{name:'Changed'}},{route:'client/account.php',user:3,post:{action:'profile',full_name:'Changed'}}]){
  for(const token of [undefined,[], 'wrong']){
   const r=route({...base,token:false,post:{...base.post,...(token===undefined?{}:{token})}});assert.equal(r.status,403);assert.equal(r.packages.find(x=>x.id===100).title,'Original');assert.equal(r.resources.find(x=>x.id===100).quantity,5);assert.equal(r.users.find(x=>x.id===3).full_name,'Client');assert.equal(r.shops.length,0);
  }
 }
 for(const user of [2,3])for(const length of [200,201,280]){
  const r=route({route:user===2?'director/account.php':'client/account.php',user,post:{update_account:'1',action:'profile',full_name:'N'.repeat(length),phone:'123'}});
  assert.equal(r.status,length===200?200:422);assert.equal(r.users.find(x=>x.id===user).full_name,length===200?'N'.repeat(length):user===2?'Director':'Client');
 }
 for(const field of ['email','password'])assert.equal(route({route:'login.php',post:{email:'x@example.invalid',password:'x',[field]:['test']}}).status,400);
 assert.equal(route({route:'client/dashboard.php',user:3,method:'GET',get:{search:'1',budget:['5']}}).status,400);
 const invalidLogin=route({route:'login.php',post:{email:'none@example.invalid',password:'x'}});assert.match(invalidLogin.body,/Invalid credentials/);
 const registered=route({route:'register.php',post:{full_name:'<script>alert(1)</script>',email:'invalid',phone:'123',password:'abc',confirm_password:'different'}});assert.match(registered.body,/&lt;script&gt;/);assert.match(registered.body,/value="invalid"/);assert.match(registered.body,/value="123"/);assert.match(registered.body,/Passwords do not match/);assert.doesNotMatch(registered.body,/<script>alert\(1\)<\/script>/);
 const saved=route({route:'client/account.php',user:3,post:{action:'profile',full_name:'New client',phone:'456',id:'2',email:'changed@example.invalid'}});assert.equal(saved.users.find(x=>x.id===3).full_name,'New client');assert.equal(saved.users.find(x=>x.id===2).full_name,'Director');
 for(const post of [{action:'password',current_password:'wrong',new_password:'NewPassword123!',confirm_password:'NewPassword123!'},{action:'password',current_password:'FixtureOldPassword!',new_password:'short',confirm_password:'short'},{action:'password',current_password:'FixtureOldPassword!',new_password:'NewPassword123!',confirm_password:'mismatch'}])assert.equal(route({route:'client/account.php',user:3,post}).status,422);
 const changedPassword=route({route:'client/account.php',user:3,post:{action:'password',current_password:'FixtureOldPassword!',new_password:'NewPassword123!',confirm_password:'NewPassword123!'}});assert.match(changedPassword.body,/Password changed/);assert.equal(changedPassword.client_password_matches_new,true);
 const linked=route({route:'client/account.php',user:3,firebase:true,post:{action:'password',current_password:'FixtureOldPassword!',new_password:'NewPassword123!',confirm_password:'NewPassword123!'}});assert.equal(linked.status,422);assert.equal(linked.client_password_matches_new,false);assert.match(linked.body,/Firebase/);
 const badBudget=route({route:'client/dashboard.php',user:3,method:'GET',get:{search:'1',budget:'-5'}});assert.equal(badBudget.status,422);assert.equal(badBudget.preferences.length,0);
 const validBudget=route({route:'client/dashboard.php',user:3,method:'GET',get:{search:'1',budget:'123.45'}});assert.equal(validBudget.status,200);assert.equal(validBudget.preferences[0].budget,123.45);
 const shopEdit=route({route:'admin/shop_action.php',user:1,shopExisting:true,post:{id:'5',name:'Updated shop',location:'Guimbal',contact_info:'123',description:'Updated'}});assert.equal(shopEdit.shops[0].name,'Updated shop');
 const shop=route({route:'admin/shop_action.php',user:1,post:{name:'Test shop',location:'Guimbal',contact_info:'123',description:'Test'}});assert.equal(shop.shops.length,1);assert.equal(shop.shops[0].name,'Test shop');
 assert.equal(route({route:'client/account.php',user:2,method:'GET'}).status,403);
 const document=route({route:'admin/serve_document.php',user:1,method:'GET',get:{type:'parlor',id:'10'}});
 assert.equal(document.status,200);assert.equal(document.body_sha256,require('crypto').createHash('sha256').update(fs.readFileSync(path.join(__dirname,'../uploads/sample-licenses/UC_Ddash.jpg'))).digest('hex'));
 assert.equal(route({route:'admin/serve_document.php',user:3,method:'GET',get:{type:'parlor',id:'10'}}).status,403);

 console.log('PASS: '+count+' selected-fix route scenarios, including no-write validation, CSRF and client account isolation.');
}finally{for(const file of fs.readdirSync(dir))fs.unlinkSync(path.join(dir,file));fs.rmdirSync(dir);}
