<?php
// clear_localstorage.php
?>
<!DOCTYPE html>
<html>
<head>
    <title>Clear LocalStorage</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 50px;
            text-align: center;
        }
        .container {
            max-width: 500px;
            margin: 0 auto;
            padding: 30px;
            border: 2px solid #ddd;
            border-radius: 10px;
            background: #f9f9f9;
        }
        h1 {
            color: #333;
        }
        .success {
            color: green;
            font-size: 18px;
            margin: 20px 0;
            padding: 10px;
            background: #d4ffd4;
            border-radius: 5px;
        }
        .info {
            margin: 20px 0;
            padding: 15px;
            background: #e7f3ff;
            border-radius: 5px;
            text-align: left;
        }
        button {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 12px 24px;
            font-size: 16px;
            border-radius: 5px;
            cursor: pointer;
            margin: 10px;
        }
        button:hover {
            background: #45a049;
        }
        button#refresh {
            background: #008CBA;
        }
        button#refresh:hover {
            background: #0077a3;
        }
        pre {
            background: #333;
            color: white;
            padding: 15px;
            border-radius: 5px;
            text-align: left;
            margin: 20px 0;
            max-height: 200px;
            overflow: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧹 Clear LocalStorage</h1>
        
        <div id="result" class="success" style="display: none;">
            LocalStorage has been cleared!
        </div>
        
        <div class="info">
            <p>This page will automatically clear your browser's localStorage when loaded.</p>
            <p>Refresh the page to clear again.</p>
        </div>
        
        <div>
            <button onclick="clearLocalStorage()">Clear LocalStorage Again</button>
            <button id="refresh" onclick="location.reload()">Refresh Page</button>
            <button onclick="window.close()">Close Window</button>
        </div>
        
        <h3>LocalStorage Contents (Before Clearing):</h3>
        <pre id="localStorageContent">Loading...</pre>
        
        <script>
            // Function to display localStorage contents
            function showLocalStorage() {
                const pre = document.getElementById('localStorageContent');
                let content = '';
                
                if (localStorage.length === 0) {
                    content = 'LocalStorage is empty';
                } else {
                    content = 'Total items: ' + localStorage.length + '\n\n';
                    for (let i = 0; i < localStorage.length; i++) {
                        const key = localStorage.key(i);
                        const value = localStorage.getItem(key);
                        content += `${i+1}. ${key}: ${value}\n`;
                    }
                }
                pre.textContent = content;
            }
            
            // Function to clear localStorage
            function clearLocalStorage() {
                const beforeCount = localStorage.length;
                localStorage.clear();
                const afterCount = localStorage.length;
                
                // Show result message
                const resultDiv = document.getElementById('result');
                resultDiv.innerHTML = `✅ Cleared ${beforeCount} items from LocalStorage`;
                resultDiv.style.display = 'block';
                
                // Update display
                showLocalStorage();
                
                // Auto-hide message after 3 seconds
                setTimeout(() => {
                    resultDiv.style.display = 'none';
                }, 3000);
            }
            
            // Clear localStorage when page loads
            window.onload = function() {
                const beforeCount = localStorage.length;
                clearLocalStorage();
                
                // Update message with count
                if (beforeCount > 0) {
                    document.getElementById('result').innerHTML = 
                        `✅ Cleared ${beforeCount} items from LocalStorage on page load`;
                    document.getElementById('result').style.display = 'block';
                }
            };
            
            // Show initial state
            document.addEventListener('DOMContentLoaded', showLocalStorage);
        </script>
    </div>
</body>
</html>