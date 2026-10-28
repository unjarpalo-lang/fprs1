// Dependency-free isolated tests. Does not start the application or use MySQL.
const fs=require('fs'),os=require('os'),path=require('path'),cp=require('child_process'),assert=require('assert/strict');
const php=process.env.PHP_BINARY || 'C:/xampp/php/php.exe';
const root=path.join(__dirname,'..'),temp=fs.mkdtempSync(path.join(os.tmpdir(),'fprs-tests-'));
function run(file,args=[]){
 const r=cp.spawnSync(php,[file,...args],{cwd:root,encoding:'utf8',env:{...process.env,FPRS_TEST_SESSION_PATH:temp},timeout:30000});
 if(r.status!==0)throw Error(file+' '+args.join(' ')+' failed: '+r.stderr+' '+r.stdout);
 return r.stdout;
}
let count=0;
try {
 process.stdout.write(run('tests/policy_test.php'));
 process.stdout.write(run('tests/recommender_test.php'));
 const fixture=name=>JSON.parse(run('tests/endpoint_fixture.php',[name]));
 const find=(rows,id)=>rows.find(r=>r.id===id);
 for(const name of ['delete_declined','delete_bad_token','delete_self','approve_missing','approve_outside','decline_parlor','package_save','package_failure','package_foreign','inventory_save','inventory_foreign','contact_outside','details_outside','feedback_outside','director_pending_catalog','client_catalog','messages_rejected','messages_recent','messages_invalid','firebase_unverified','firebase_verified','firebase_link_conflict','maintenance_http','client_dashboard','admin_declined_catalog','package_form','client_inc_false_positive','client_inc_acronym','client_same_municipality','client_low_budget','maintenance_extract_http','director_outside_dashboard','inquiry_contact_get','inquiry_contact_post','inquiry_bad_token','inquiry_package_mismatch','inquired_services','feedback_form','feedback_submit','feedback_foreign','feedback_duplicate','feedback_cancelled','feedback_bad_token','feedback_invalid','director_inquiry_foreign','director_inquiry_repeat','package_rating_api','original_reviews','inquired_confirmed_services','feedback_unconfirmed','feedback_unconfirmed_form','feedback_incomplete','feedback_spoof_confirm','director_inquiry_confirm','director_inquiry_client','inquiry_again_get','inquiry_again_post','inquiry_active_post','inquired_availed_services']){
  const r=fixture(name);
  switch(name){
   case 'delete_declined':assert.equal(find(r.users,5),undefined);break;
   case 'delete_bad_token':assert.equal(r.status,403);assert.ok(find(r.users,5));break;
   case 'delete_self':assert.ok(find(r.users,1));break;
   case 'approve_missing':assert.equal(find(r.parlors,10).status,'pending');assert.match(r.notice,/business license/);break;
   case 'approve_outside':assert.equal(find(r.parlors,11).status,'approved');assert.match(r.notice,/Guimbal or Miagao/);break;
   case 'decline_parlor':assert.equal(find(r.parlors,10).status,'rejected');break;
   case 'package_save':assert.equal(find(r.packages,100).title,'Changed');assert.equal(r.inclusions[0].service_name,'New service');break;
   case 'package_failure':assert.equal(find(r.packages,100).title,'Original');assert.equal(r.inclusions[0].service_name,'Original service');assert.match(r.body,/could not be saved/);break;
   case 'package_foreign':assert.equal(r.status,403);assert.equal(find(r.packages,102).title,'Other package');assert.equal(r.inclusions[0].service_name,'Original service');break;
   case 'inventory_save':assert.equal(find(r.resources,100).available_status,'missing');assert.equal(find(r.resources,100).quantity,0);break;
   case 'inventory_foreign':assert.equal(r.status,403);assert.equal(find(r.resources,102).name,'Other resource');break;
   case 'contact_outside':case 'details_outside':assert.equal(r.status,404);break;
   case 'feedback_outside':assert.equal(r.status,403);break;
   case 'director_outside_dashboard':assert.match(r.body,/Guimbal and Miagao service area/);assert.match(r.body,/Manage Packages/);assert.match(r.body,/Manage Inventory/);assert.match(r.body,/Manage Account/);break;
   case 'director_pending_catalog':assert.match(r.body,/Original/);break;
   case 'client_catalog':assert.match(r.body,/Original/);assert.doesNotMatch(r.body,/Outside package/);break;
   case 'messages_rejected':assert.equal(r.status,403);break;
   case 'messages_recent':{const m=JSON.parse(r.body);assert.equal(m.length,200);assert.equal(m[0].id,6);assert.equal(m[199].id,205);break;}
   case 'messages_invalid':assert.equal(r.status,400);break;
   case 'firebase_unverified':assert.equal(r.status,403);assert.equal(find(r.users,3).firebase_uid,null);break;
   case 'firebase_verified':assert.equal(find(r.users,3).firebase_uid,'fixture-uid');assert.equal(JSON.parse(r.body).ok,true);break;
   case 'firebase_link_conflict':assert.equal(r.status,409);assert.equal(find(r.users,3).firebase_uid,'other-uid');break;
   case 'client_inc_false_positive':assert.match(r.body,/Package includes transport/);assert.doesNotMatch(r.body,/Religion compatibility unconfirmed|Distance unavailable|boundary crossing|barangay centers/);break;
   case 'client_inc_acronym':assert.match(r.body,/INC Memorial/);break;
   case 'client_same_municipality': case 'client_low_budget':assert.match(r.body,/Original/);assert.match(r.body,/Comprehensive local package/);assert.match(r.body,/Above your budget/);assert.doesNotMatch(r.body,/Religion compatibility unconfirmed|Distance unavailable|boundary crossing|barangay centers/);assert.ok(r.body.indexOf('Comprehensive local package') < r.body.indexOf('>Original<'));assert.doesNotMatch(r.body,/Outside package/);break;
   case 'maintenance_extract_http':assert.equal(r.status,404);break;
   case 'client_dashboard':assert.doesNotMatch(r.body,/client\/feedback.php/);assert.match(r.body,/data-package-rating-id/);assert.match(r.body,/Original/);assert.doesNotMatch(r.body,/Outside package|Outside without package/);assert.doesNotMatch(r.body,/Same barangay|Distance unavailable|boundary crossing/);assert.match(r.body,/Scoped without package/);break;
   case 'admin_declined_catalog':assert.match(r.body,/data-delete-url/);assert.match(r.body,/return_to=/);break;
   case 'package_form':assert.match(r.body,/Back to packages/);break;
   case 'inquiry_contact_get':assert.equal(r.inquiries.length,0);assert.match(r.body,/Send Inquiry/);break;
   case 'inquiry_contact_post':assert.equal(r.inquiries.length,1);assert.equal(r.message_count,1);break;
   case 'inquired_availed_services':assert.match(r.body,/Inquire Again/);assert.match(r.body,/Leave Feedback/);break;
   case 'inquiry_again_get':assert.match(r.body,/name="after_inquiry_id" value="1"/);assert.match(r.body,/Send Inquiry/);break;
   case 'inquiry_again_post':assert.equal(r.inquiries.length,2);assert.equal(r.inquiries[1].status,'inquired');assert.equal(r.inquiries[1].confirmed_by,null);assert.equal(r.inquiries[1].occurrence_no,2);assert.equal(r.message_count,1);break;
   case 'inquiry_active_post':assert.equal(r.inquiries.length,1);assert.equal(r.message_count,0);break;
   case 'inquiry_bad_token':assert.equal(r.status,403);assert.equal(r.inquiries.length,0);break;
   case 'inquiry_package_mismatch':assert.equal(r.status,404);assert.equal(r.inquiries.length,0);break;
   case 'inquired_services':assert.doesNotMatch(r.body,/Leave Feedback/);assert.match(r.body,/Awaiting director confirmation/);assert.doesNotMatch(r.body,/Experience #/);assert.equal(r.inquiries[0].occurrence_no,1);break;
   case 'inquired_confirmed_services':assert.match(r.body,/Leave Feedback/);assert.match(r.body,/Confirmed/);break;
   case 'feedback_form':assert.match(r.body,/Submit Feedback/);assert.match(r.body,/name="token"/);assert.equal((r.body.match(/<fieldset[^>]*role="radiogroup"/g)||[]).length,22);assert.match(r.body,/Strongly Agree/);assert.match(r.body,/Location of the Funeral Homes is easy to locate/);break;
   case 'feedback_submit':assert.equal(r.package_feedback.length,1);assert.equal(r.package_feedback[0].rating,2);assert.equal(r.questionnaire_answers.length,22);break;
   case 'feedback_foreign':case 'feedback_bad_token':assert.equal(r.status,403);assert.equal(r.package_feedback.length,0);break;
   case 'feedback_duplicate':assert.equal(r.status,409);assert.equal(r.package_feedback.length,1);assert.equal(r.package_feedback[0].rating,5);break;
   case 'feedback_cancelled':assert.equal(r.status,409);assert.equal(r.package_feedback.length,0);break;
   case 'director_inquiry_client':assert.equal(r.status,403);assert.equal(r.inquiries[0].confirmed_by,null);break;
   case 'director_inquiry_confirm':assert.equal(r.inquiries[0].status,'confirmed');assert.equal(r.inquiries[0].confirmed_by,2);assert.ok(r.inquiries[0].confirmed_at);break;
   case 'feedback_unconfirmed':case 'feedback_spoof_confirm':assert.equal(r.status,409);assert.equal(r.package_feedback.length,0);assert.equal(r.questionnaire_answers.length,0);break;
   case 'feedback_unconfirmed_form':assert.doesNotMatch(r.body,/Submit Feedback/);assert.match(r.body,/Awaiting director confirmation/);break;
   case 'feedback_incomplete':assert.equal(r.status,422);assert.equal(r.package_feedback.length,0);break;
   case 'feedback_invalid':assert.equal(r.status,422);assert.equal(r.package_feedback.length,0);break;
   case 'director_inquiry_foreign':assert.equal(r.status,409);assert.equal(r.inquiries.length,1);break;
   case 'director_inquiry_repeat':assert.equal(r.status,409);assert.equal(r.inquiries.length,1);break;
   case 'original_reviews':assert.match(r.body,/data-parlor-rating="true"/);assert.doesNotMatch(r.body,/Recorded original review|Client reviews|aria-label="Package reviews"/);assert.doesNotMatch(r.body,/No reviews yet|No package reviews yet/);assert.doesNotMatch(r.body,/Original parlor ratings|Original client reviews|Original recorded review/);assert.match(r.body,/4.0/);assert.doesNotMatch(r.body,/client\/feedback.php/);break;
   case 'package_rating_api':assert.deepEqual(JSON.parse(r.body).ratings['100'],{average:5,count:1});break;
   case 'maintenance_http':assert.equal(r.status,404);break;
  }
  count++;
 }
 console.log('PASS: '+count+' isolated endpoint scenarios (SQLite and mocked Firebase).');
} finally {
 // Only files in this unique test-session directory are removed.
 for(const name of fs.readdirSync(temp)) fs.unlinkSync(path.join(temp,name));
 fs.rmdirSync(temp);
}
