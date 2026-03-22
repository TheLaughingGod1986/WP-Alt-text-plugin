const puppeteer = require('puppeteer');

(async () => {
  const browser = await puppeteer.launch({ headless: false });
  const page = await browser.newPage();
  
  try {
    // Navigate to login page
    await page.goto('http://localhost:8080/wp-login.php?redirect_to=http%3A%2F%2Flocalhost%3A8080%2Fwp-admin%2Fadmin.php%3Fpage%3Dbbai');
    
    // Fill in credentials
    await page.type('#user_login', 'admin');
    await page.type('#user_pass', 'admin123');
    
    // Click login button
    await page.click('#wp-submit');
    
    // Wait for navigation
    await page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 10000 });
    
    console.log('Logged in successfully!');
    console.log('Current URL:', page.url());
    
    // Keep browser open for inspection
    await new Promise(resolve => setTimeout(resolve, 60000));
    
  } catch (error) {
    console.error('Login failed:', error);
  } finally {
    await browser.close();
  }
})();
