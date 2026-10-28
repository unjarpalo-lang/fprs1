// Exercise the real refresh script with a simulated DOM and committed API summaries.
const fs = require('fs'), vm = require('vm'), assert = require('assert/strict');
(async () => {
  const node = {dataset: {packageRatingId: '100'}, style: {}, textContent: 'Old score'};
  const handlers = {}, channels = [];
  let summary = {average: 5, count: 1}, calls = 0, timer;
  const document = {hidden: false, currentScript: {dataset: {endpoint: '/api/package_ratings.php', reviewedPackage: '100'}}, querySelectorAll: () => [node], addEventListener: (name, fn) => {handlers[name] = fn;}};
  class Channel { constructor() {channels.push(this);} postMessage(value) {this.sent = value;} }
  const window = {BroadcastChannel: Channel, addEventListener: (name, fn) => {handlers[name] = fn;}};
  vm.runInNewContext(fs.readFileSync(require('path').join(__dirname,'../assets/js/package-ratings.js'),'utf8'), {
    document, window, BroadcastChannel: Channel, setInterval: fn => {timer = fn;},
    fetch: async () => {calls++; return {ok: true, json: async () => ({ratings: {'100': summary}})};},
  });
  assert.equal(channels[0].sent, '100');
  await timer(); assert.match(node.textContent,/5.0\/5 \(1 review\)/);
  summary = {average: 3, count: 2}; await channels[0].onmessage(); assert.match(node.textContent,/3.0\/5 \(2 reviews\)/);
  document.hidden = true; const previous = calls; await timer(); assert.equal(calls, previous);
  document.hidden = false; summary = {average: 4, count: 3}; await handlers.focus(); assert.match(node.textContent,/4.0\/5/);
  summary = {average: 0, count: 0}; await timer(); assert.equal(node.textContent,'No reviews yet');
  node.dataset.hasRecordedReviews = '1'; await timer(); assert.equal(node.textContent,''); assert.equal(node.style.display,'none');
  summary = {average: 5, count: 1}; await timer(); assert.equal(node.style.display,''); assert.match(node.textContent,/5.0\/5/);
  console.log('PASS: live package rating refresh, cross-tab notification, average changes, and hidden-tab pause.');
})().catch(error => {console.error(error); process.exitCode=1;});
