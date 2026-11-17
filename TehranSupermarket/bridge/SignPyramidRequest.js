'use strict';
// One process, one signature. Private key remains on this authorized Windows host.
const fs=require('fs');
const crypto=require('crypto');
const path=require('path');
const argv=process.argv.slice(2);
if(argv.length!==6)process.exit(2);
const [method,query,timestamp,device,requestId,bodySha256]=argv;
if(!/^(GET|POST)$/.test(method) ||
   !/^[0-9]{10,11}$/.test(timestamp) ||
   device!=='EXSOM-J3' ||
   !/^[a-zA-Z0-9._:-]{12,120}$/.test(requestId) ||
   !/^[a-f0-9]{64}$/.test(bodySha256) ||
   !/^action=[a-z_]+(?:&after_id=[0-9]{1,13})?$/.test(query))process.exit(3);
const privateKeyPath=path.join(__dirname,'keys','private.pem');
const key=fs.readFileSync(privateKeyPath,'utf8');
const canonical=['v1',method,query,timestamp,device,requestId,bodySha256].join('\n');
const signature=crypto.sign('RSA-SHA256',Buffer.from(canonical,'utf8'),key);
process.stdout.write(signature.toString('base64')+'\n');
