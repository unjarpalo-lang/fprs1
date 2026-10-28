// Verify the deployed service statements against the supplied Word questionnaire.
const fs=require('fs'), zlib=require('zlib'), cp=require('child_process'), path=require('path'), assert=require('assert/strict');
const root=path.resolve(__dirname,'..');
const zip=fs.readFileSync(path.join(root,'Questionnaire4RespondentsNotFinished (1).docx'));
const end=zip.lastIndexOf(Buffer.from([0x50,0x4b,0x05,0x06]));
assert.ok(end>=0,'ZIP directory exists');
let offset=zip.readUInt32LE(end+16), xml;
for(let index=0; index<zip.readUInt16LE(end+10); index++) {
  assert.equal(zip.readUInt32LE(offset),0x02014b50);
  const nameLength=zip.readUInt16LE(offset+28),extra=zip.readUInt16LE(offset+30),comment=zip.readUInt16LE(offset+32);
  const name=zip.subarray(offset+46,offset+46+nameLength).toString();
  if(name==='word/document.xml') {
    const local=zip.readUInt32LE(offset+42), start=local+30+zip.readUInt16LE(local+26)+zip.readUInt16LE(local+28);
    const bytes=zip.subarray(start,start+zip.readUInt32LE(offset+20));
    xml=(zip.readUInt16LE(offset+10)===8 ? zlib.inflateRawSync(bytes) : bytes).toString();
    break;
  }
  offset+=46+nameLength+extra+comment;
}
assert.ok(xml,'Questionnaire XML exists');
const decode=text=>text.replace(/&amp;/g,'&').replace(/&lt;/g,'<').replace(/&gt;/g,'>').replace(/&quot;/g,'"').replace(/&apos;/g,"'");
const paragraphs=[...xml.matchAll(/<w:p\b[^>]*>([\s\S]*?)<\/w:p>/g)].map(p=>decode([...p[1].matchAll(/<w:t\b[^>]*>([\s\S]*?)<\/w:t>/g)].map(t=>t[1]).join('')).replace(/\s+/g,' ').trim());
const json=cp.execFileSync(process.env.PHP_BINARY||'C:/xampp/php/php.exe',['-r',"require 'includes/feedback_questionnaire.php';echo json_encode(feedback_questionnaire());"],{cwd:root,encoding:'utf8'});
const groups=JSON.parse(json), statements=Object.values(groups).flatMap(group=>group.statements);
assert.equal(statements.length,22);
assert.deepEqual(Object.values(groups).map(group=>group.statements.length),[3,5,2,2,4,3,2,1]);
for(const statement of statements) assert.ok(paragraphs.includes(statement),'Exact source statement: '+statement);
console.log('PASS: all 22 service statements match the supplied Word questionnaire, with the original eight-group structure.');
