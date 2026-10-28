// Provider flow contract checks with a fake SDK; no cloud account is modified.
const fs=require('fs'),vm=require('vm'),assert=require('assert/strict');
const code=fs.readFileSync(__dirname+'/../assets/js/client-account.js','utf8').replace(/^import .*;$/gm,'').replace('export function','function');
async function scenario({uid='expected',confirm='NewPassword123!',failure=null}={}){
 const calls=[],values={current_password:'CurrentPassword!',new_password:'NewPassword123!',confirm_password:confirm};
 const button={dataset:{}},status={},form={dataset:{uid:'expected',email:'client@example.invalid'},reportValidity:()=>true,addEventListener:(name,fn)=>form.submit=fn,reset:()=>calls.push('reset')};
 const elements={'firebase-password-form':form,'firebase-password-submit':button,'firebase-password-status':status};
 const context={document:{getElementById:id=>elements[id]},FormData:class{get(key){return values[key]}},initializeApp:()=>({}),getFirebaseApp:()=>({options:{}}),initializeAuth:(app,options)=>{assert.ok(options.persistence);return{}},inMemoryPersistence:{},signInWithEmailAndPassword:async()=>{calls.push('signin');if(failure)throw {code:failure};return{user:{uid}}},EmailAuthProvider:{credential:()=>({})},reauthenticateWithCredential:async user=>{assert.equal(user.uid,'expected');calls.push('reauth')},updatePassword:async(user,password)=>{assert.equal(user.uid,'expected');assert.equal(password,values.new_password);calls.push('update')},signOut:async()=>calls.push('signout')};
 vm.createContext(context);vm.runInContext(code,context);context.attachPasswordChange();await form.submit({preventDefault(){}});return {calls,status,button};
}
(async()=>{
 const ok=await scenario();assert.deepEqual(ok.calls,['signin','reauth','update','reset','signout']);assert.match(ok.status.textContent,/password changed/);assert.equal(ok.button.disabled,false);
 const mismatch=await scenario({uid:'someone-else'});assert.ok(!mismatch.calls.includes('update'));assert.match(mismatch.status.textContent,/not changed/);
 const passwords=await scenario({confirm:'different'});assert.deepEqual(passwords.calls,[]);assert.match(passwords.status.textContent,/do not match/);
 const offline=await scenario({failure:'auth/network-request-failed'});assert.ok(!offline.calls.includes('update'));assert.match(offline.status.textContent,/Connection failed/);assert.ok(offline.calls.includes('signout'));
 console.log('PASS: Firebase reauthentication order, UID isolation, password mismatch and failure cleanup (mock SDK).');
})().catch(e=>{console.error(e);process.exitCode=1});
