module.exports = {
  php: "C:/xampp/php/php.exe",  // Path to PHP executable
  open: "Projectni/index.php",   // File to open on start (relative to project root)
  host: "0.0.0.0",               // Allow access from other devices
  port: 3000,                     // FiveServer port

  // Proxy PHP requests to Apache running via XAMPP
  proxy: "http://localhost:8080/Projectni/"
};
