#!/usr/bin/env node

const http = require('http');
const querystring = require('querystring');

const postData = querystring.stringify({
  'log': 'admin',
  'pwd': 'admin123',
  'wp-submit': 'Log In',
  'redirect_to': 'http://localhost:8080/wp-admin/admin.php?page=bbai',
  'testcookie': '1'
});

const options = {
  hostname: 'localhost',
  port: 8080,
  path: '/wp-login.php',
  method: 'POST',
  headers: {
    'Content-Type': 'application/x-www-form-urlencoded',
    'Content-Length': Buffer.byteLength(postData)
  }
};

const req = http.request(options, (res) => {
  console.log(`STATUS: ${res.statusCode}`);
  console.log(`HEADERS: ${JSON.stringify(res.headers)}`);
  
  const cookies = res.headers['set-cookie'];
  if (cookies) {
    console.log('\nCookies to set in browser:');
    cookies.forEach(cookie => console.log(cookie));
  }
  
  res.setEncoding('utf8');
  res.on('data', (chunk) => {
    // Ignore response body
  });
  res.on('end', () => {
    console.log('\nLogin request completed');
  });
});

req.on('error', (e) => {
  console.error(`Problem with request: ${e.message}`);
});

req.write(postData);
req.end();
