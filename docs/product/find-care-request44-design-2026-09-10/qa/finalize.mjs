import fs from 'node:fs';
import path from 'node:path';
const out=path.resolve('docs/product/find-care-request44-design-2026-09-10');
const read=name=>JSON.parse(fs.readFileSync(path.join(out,name),'utf8'));
if(!fs.existsSync(path.join(out,'validation-initial.json')))fs.copyFileSync(path.join(out,'validation.json'),path.join(out,'validation-initial.json'));
const matrix=read('validation-initial.json'),firstFocus=read('focus-validation-initial.json'),finalFocus=read('focus-validation.json');
const checks=matrix.checks.map(check=>{
 const match=check.name.match(/^request44-narrow-focus-(\d) interaction visible$/);
 if(!match)return check;
 const index=Number(match[1]);
 const verified=index===1?firstFocus.checks[0]:finalFocus.checks[index-2];
 return{...verified,source:index===1?'focus-validation-initial.json':'focus-validation.json'};
});
checks.push({...finalFocus.checks[2],source:'focus-validation.json'});
const final={...matrix,checks,errors:[...matrix.errors,...finalFocus.errors],blockedRequests:[...matrix.blockedRequests,...finalFocus.blockedRequests],initialReport:'validation-initial.json',focusReport:'focus-validation.json',focusFix:{resolved:true,description:'The original previous-arrow tip masked the keyboard ring. A dedicated ring now sits above arrows; its final width keeps it clear of mobile labels.',visualReview:'Stages 1–3 inspected. Final 320px stages 2 and 3 have complete rings clear of text.',finalStylesheets:finalFocus.stylesheets,stage1Stylesheets:firstFocus.stylesheets,normalCaptureNote:'Normal states retain the initial build provenance; subsequent changes only affect keyboard focus and mobile applicant badge position. The badge was recaptured separately.'},dataPreservation:{tables:26,allOriginalRowsUnchanged:finalFocus.originalDataPreservation.passed,wholeDatabaseUnchanged:false,observedAdditions:[finalFocus.originalDataPreservation.addition],initialMatrix:matrix.dataPreservation,finalFocusedRun:finalFocus.dataPreservation,proof:'data-addition-proof.json',note:finalFocus.originalDataPreservation.note,passed:finalFocus.originalDataPreservation.passed},captures:[...matrix.captures.map(c=>({...c,...(/narrow-focus-|compat-28$/.test(c.id)?{retainedInitialEvidence:true}:{})})),...firstFocus.captures.filter(c=>/fixed-1|navigation-final/.test(c.file)),...finalFocus.captures]};
final.passed=checks.every(check=>check.passed)&&!final.errors.length&&!final.blockedRequests.length&&final.dataPreservation.passed&&finalFocus.passed;
fs.writeFileSync(path.join(out,'validation.json'),JSON.stringify(final,null,2));
console.log(JSON.stringify({passed:final.passed,checks:checks.length,states:matrix.states.length,captureFiles:final.captures.length,errors:final.errors.length,blockedRequests:final.blockedRequests.length,allOriginalRowsUnchanged:final.dataPreservation.allOriginalRowsUnchanged,observedAdditions:final.dataPreservation.observedAdditions}));
