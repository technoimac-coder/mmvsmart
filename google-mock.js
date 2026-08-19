/**
 * google-mock.js
 * Comprehensive mock for google.script.run to route all calls to api.php directly
 * Instant native Print / Save as PDF via hidden in-page frame (NO new tab opened, NO hanging!)
 */

// Create mock google namespace
window.google = window.google || {};
window.google.script = window.google.script || {};

window.google.script.run = new Proxy({}, {
  get: function(target, prop) {
    if (prop === 'withSuccessHandler') {
      return function(onSuccess) {
        return createRunnerProxy({ success: onSuccess });
      };
    }
    if (prop === 'withFailureHandler') {
      return function(onFailure) {
        return createRunnerProxy({ failure: onFailure });
      };
    }
    if (prop === 'withUserObject') {
      return function(userObj) {
        return createRunnerProxy({ userObject: userObj });
      };
    }
    // Default call with no handlers
    return function(...args) {
      callServer(prop, args);
    };
  }
});

function createRunnerProxy(handlers = {}) {
  const runnerHandler = {
    get: function(target, prop) {
      if (prop === 'withSuccessHandler') {
        return function(onSuccess) {
          target.success = onSuccess;
          return new Proxy(target, runnerHandler);
        };
      }
      if (prop === 'withFailureHandler') {
        return function(onFailure) {
          target.failure = onFailure;
          return new Proxy(target, runnerHandler);
        };
      }
      if (prop === 'withUserObject') {
        return function(userObj) {
          target.userObject = userObj;
          return new Proxy(target, runnerHandler);
        };
      }
      // prop is the server-side method name to execute
      return function(...args) {
        callServer(prop, args, target.success, target.failure);
      };
    }
  };
  return new Proxy(handlers, runnerHandler);
}

function callServer(action, args, successCallback, failureCallback) {
  const isPdfAction = action.startsWith('generate') || action.includes('PDF');
  
  if (isPdfAction && window.Swal) {
    Swal.fire({
      title: 'กำลังเตรียมรายงาน...',
      text: 'กรุณารอสักครู่ ระบบกำลังดึงข้อมูล',
      allowOutsideClick: false,
      showConfirmButton: false,
      timer: 800,
      didOpen: () => {
        Swal.showLoading();
      }
    });
  }

  fetch('api.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ action: action, args: args })
  })
  .then(res => {
    if (!res.ok) {
      throw new Error('Server error: ' + res.status);
    }
    return res.json();
  })
  .then(data => {
    if (window.Swal && Swal.isVisible()) {
      Swal.close();
    }
    if (data && data.success === false && data.message) {
      if (failureCallback) {
        failureCallback(new Error(data.message));
      } else if (successCallback) {
        successCallback(data);
      }
    } else if (data && data.html && isPdfAction) {
      // Print or Save PDF directly in the current page via hidden iframe without opening a new tab
      printInPageDirectly(data.html, data.filename || 'report.pdf');
      if (successCallback) {
        successCallback({ success: true, filename: data.filename || 'report.pdf' });
      }
    } else {
      if (successCallback) {
        successCallback(data);
      }
    }
  })
  .catch(err => {
    if (window.Swal && Swal.isVisible()) {
      Swal.close();
    }
    console.error('callServer error:', err);
    if (failureCallback) {
      failureCallback(err);
    }
  });
}

function printInPageDirectly(htmlContent, filename) {
  const isOverall = filename.startsWith('Overall_Report');
  
  // Format HTML with print styling
  let formattedHtml = `<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>${filename}</title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&display=swap">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&display=swap');
    @page {
      size: A4 portrait;
      margin: 5mm 8mm 5mm 8mm !important;
    }
    * {
      box-sizing: border-box;
      font-family: 'Sarabun', Tahoma, sans-serif !important;
    }
    html, body {
      margin: 0 !important;
      padding: 0 !important;
      background: #ffffff !important;
      color: #000000 !important;
      width: 100% !important;
      font-family: 'Sarabun', Tahoma, sans-serif !important;
      font-size: 10.5pt !important;
      ${isOverall ? 'height: 100% !important; overflow: hidden !important;' : ''}
    }
    .print-container {
      width: 100% !important;
      max-width: 100% !important;
      margin: 0 !important;
      padding: 0 !important;
      ${isOverall ? 'page-break-after: avoid !important; break-after: avoid !important;' : ''}
    }
    table {
      width: 100% !important;
      border-collapse: collapse !important;
      margin-top: 2px !important;
      margin-bottom: 3px !important;
    }
    th, td {
      border: 0.5px solid #555555 !important;
      text-align: center;
      font-size: 10.5pt !important;
      line-height: 1.2 !important;
      vertical-align: middle !important;
      font-family: 'Sarabun', Tahoma, sans-serif !important;
    }
    th, th * {
      background-color: #f2f2f2 !important;
      font-weight: bold !important;
      border: 0.5px solid #333333 !important;
      padding: 2.5px 4px !important;
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
    }
    td, td * {
      font-weight: normal !important;
      padding: 1.5px 4px !important;
    }
    td.text-left, th.text-left, td[style*="text-align:left"], td[style*="text-align: left"], th[style*="text-align:left"], th[style*="text-align: left"] {
      text-align: left !important;
      padding-left: 6px !important;
    }
    .total-row td, .total-row td * {
      font-weight: bold !important;
      padding: 1.5px 4px !important;
    }
    .header-table td, .header-table th, .header-table tr, .header-table {
      border: none !important;
    }
    .logo {
      width: 62px !important;
      height: auto !important;
      display: block !important;
    }
    .signature-section {
      page-break-inside: avoid !important;
      break-inside: avoid !important;
      margin-top: ${isOverall ? '12px' : '15px'} !important;
    }
    .signature-section table, .signature-section td, .signature-section th, .signature-section tr {
      border: none !important;
      font-size: 10pt !important;
      line-height: 1.35 !important;
    }
    .signature-title {
      text-align: center !important;
      font-size: 10.5pt !important;
      margin-bottom: ${isOverall ? '25px' : '20px'} !important;
    }
    h2 {
      font-size: 13.5pt !important;
      font-weight: bold !important;
      margin: 0 0 2px 0 !important;
      text-align: center !important;
    }
    h3 {
      font-size: 11.5pt !important;
      font-weight: bold !important;
      margin: 3px 0 2px 0 !important;
      text-align: center !important;
    }
    p {
      font-size: 10.5pt !important;
      margin: 2px 0 6px 0 !important;
      text-align: center !important;
    }
    h4 {
      font-size: 10.5pt !important;
      font-weight: bold !important;
      margin: 3px 0 4px 0 !important;
      text-align: center !important;
    }
  </style>
</head>
<body>
  ${htmlContent}
</body>
</html>`;

  // Use a hidden iframe to trigger the native browser print/save-as-PDF window instantly
  let iframe = document.getElementById('report-silent-frame');
  if (iframe) {
    document.body.removeChild(iframe);
  }
  
  iframe = document.createElement('iframe');
  iframe.id = 'report-silent-frame';
  iframe.style.position = 'fixed';
  iframe.style.right = '0';
  iframe.style.bottom = '0';
  iframe.style.width = '0';
  iframe.style.height = '0';
  iframe.style.border = '0';
  iframe.style.visibility = 'hidden';
  document.body.appendChild(iframe);

  const doc = iframe.contentWindow.document;
  doc.open();
  doc.write(formattedHtml);
  doc.close();

  iframe.contentWindow.focus();
  setTimeout(() => {
    try {
      iframe.contentWindow.print();
    } catch (e) {
      console.error('Frame print error:', e);
    }
  }, 400);
}
