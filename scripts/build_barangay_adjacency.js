const fs = require('fs');
const crypto = require('crypto');
const path = require('path');
const source = 'https://portal.georisk.gov.ph/arcgis/rest/services/PSA/Barangay/MapServer/4';
(async () => {
 const url = new URL(source + '/query');
 url.search = new URLSearchParams({where: "prov_name='Iloilo' AND city_name IN ('Guimbal','Miagao')",outFields:'objectid,city_name,brgy_name,brgy_code',returnGeometry:'true',outSR:'4326',f:'json'});
 const response = await fetch(url,{signal:AbortSignal.timeout(30000)});
 if (!response.ok) throw Error('Boundary download HTTP '+response.status);
 const raw = await response.text(), data = JSON.parse(raw);
 if(data.error || data.exceededTransferLimit) throw Error('Incomplete boundary response');
 const php = fs.readFileSync(path.join(__dirname, '../includes/iloilo_locations.php'),'utf8');
 const locations = {};
 for(const municipality of ['Guimbal','Miagao']) {
  const block = php.match(new RegExp("'"+municipality+"' => \\[([\\s\\S]*?)\\],"));
  if(!block)throw Error('Missing municipality '+municipality);
  locations[municipality]=[...block[1].matchAll(/'([^']+)'/g)].map(m=>m[1]);
 }
 const graph={}, codes={}, originalNames={}, edges=new Map();
 const aliases={'Guimbal|Bagumbayan Pob. (Bagumbayan-Granada)':'Bagumbayan Poblacion'};
 for(const f of data.features) {
  const a=f.attributes;
  const name=aliases[a.city_name+'|'+a.brgy_name] || a.brgy_name.replace(/\s*\([^)]*\)/g,'').trim();
  if(!locations[a.city_name]?.includes(name))throw Error('Unmapped boundary '+a.city_name+'|'+a.brgy_name);
  const key=name+', '+a.city_name;
  if(graph[key])throw Error('Duplicate boundary '+key);
  graph[key]=new Set();codes[key]=a.brgy_code;originalNames[key]=a.brgy_name;
  if(!f.geometry?.rings?.length)throw Error('Missing polygon '+key);
  for(const ring of f.geometry.rings) {
   for(let i=1;i<ring.length;i++){
    const p=JSON.stringify(ring[i-1]),q=JSON.stringify(ring[i]);
    if(p===q)continue;
    const edge=p<q?p+'|'+q:q+'|'+p;
    if(!edges.has(edge))edges.set(edge,new Set());
    edges.get(edge).add(key);
   }
  }
 }
 for(const [m,names] of Object.entries(locations))for(const name of names)if(!graph[name+', '+m])throw Error('Missing boundary '+name+', '+m);
 for(const owners of edges.values())for(const a of owners)for(const b of owners)if(a!==b)graph[a].add(b);
 const visited=new Set(),components=[];
 for(const start of Object.keys(graph)){if(visited.has(start))continue;const queue=[start];visited.add(start);for(let i=0;i<queue.length;i++)for(const next of graph[queue[i]])if(!visited.has(next)){visited.add(next);queue.push(next);}components.push(queue);}
 if(components.length!==1)throw Error('Boundary graph is disconnected; review geometry before use');
 const adjacency=Object.fromEntries(Object.keys(graph).sort().map(k=>[k,[...graph[k]].sort()]));
 const out={metadata:{source,description:'PSA indicative barangay boundaries based on the 2015 census, observed June 2016; published by GeoRisk PH.',retrieved_date:new Date().toISOString().slice(0,10),method:'Two polygons are adjacent only when they share an exact, non-zero boundary segment; point contacts excluded. Shortest paths remain within Guimbal and Miagao.',source_sha256:crypto.createHash('sha256').update(raw).digest('hex'),counts:Object.fromEntries(Object.entries(locations).map(([m,a])=>[m,a.length])),components:components.map(c=>c.length),edge_count:Object.values(adjacency).reduce((n,a)=>n+a.length,0)/2},codes,source_names:originalNames,adjacency};
 process.stdout.write(JSON.stringify(out));
})().catch(e=>{console.error(e.message);process.exit(1);});
