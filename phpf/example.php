<!DOCTYPE html>
<html>
<head>
    <title>Embed PDF Viewer Example</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #f0f2f5;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        h1 {
            color: #2c3e50;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        h2 {
            color: #3498db;
            margin-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
        }
        
        .embed-container {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
            margin: 20px 0;
            background: #f8f9fa;
        }
        
        iframe {
            width: 100%;
            border: none;
        }
        
        .iframe-large {
            height: 600px;
        }
        
        .iframe-medium {
            height: 400px;
        }
        
        .iframe-small {
            height: 300px;
        }
        
        .code-block {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            margin: 15px 0;
            font-family: 'Courier New', monospace;
            font-size: 14px;
        }
        
        .controls {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin: 15px 0;
        }
        
        .btn {
            padding: 10px 20px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .btn:hover {
            background: #2980b9;
        }
        
        .btn-secondary {
            background: #95a5a6;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
        }
        
        .pdf-selector {
            display: flex;
            gap: 10px;
            align-items: center;
            margin: 20px 0;
        }
        
        select {
            padding: 10px 15px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            min-width: 200px;
        }
        
        .size-controls {
            display: flex;
            gap: 10px;
            align-items: center;
            margin: 20px 0;
        }
        
        .size-btn {
            padding: 8px 15px;
            background: #ecf0f1;
            border: 2px solid #bdc3c7;
            border-radius: 5px;
            cursor: pointer;
        }
        
        .size-btn.active {
            background: #3498db;
            color: white;
            border-color: #2980b9;
        }
        
        .demo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .demo-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .demo-card h3 {
            color: #2c3e50;
            margin-bottom: 15px;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .section {
                padding: 15px;
            }
            
            .iframe-large {
                height: 400px;
            }
            
            .iframe-medium {
                height: 300px;
            }
            
            .iframe-small {
                height: 200px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📄 Embeddable PDF Viewer Demo</h1>
        
        <div class="section">
            <h2>Embed PDF Viewer in Your Website</h2>
            
            <div class="pdf-selector">
                <label for="pdfSelect">Select PDF:</label>
                <select id="pdfSelect" onchange="changePDF(this.value)">
                    <option value="test.pdf">test.pdf</option>
                    <option value="document.pdf">document.pdf</option>
                    <option value="report.pdf">report.pdf</option>
                    <!-- Add more PDFs as needed -->
                </select>
            </div>
            
            <div class="size-controls">
                <span>Size:</span>
                <button class="size-btn active" onclick="changeSize('large')">Large</button>
                <button class="size-btn" onclick="changeSize('medium')">Medium</button>
                <button class="size-btn" onclick="changeSize('small')">Small</button>
            </div>
            
            <div class="embed-container">
                <iframe id="pdfEmbed" 
                        src="view.php?file=test.pdf" 
                        class="iframe-large"
                        allowfullscreen>
                </iframe>
            </div>
            
            <div class="controls">
                <button class="btn" onclick="reloadViewer()">🔄 Reload</button>
                <button class="btn" onclick="openInNewTab()">📄 Open Full View</button>
                <button class="btn" onclick="toggleFullscreen()">🔍 Fullscreen</button>
                <button class="btn btn-secondary" onclick="showCode()">📋 Show Embed Code</button>
            </div>
        </div>
        
        <div class="section">
            <h2>Embed Code Examples</h2>
            
            <h3>Basic Embed:</h3>
            <div class="code-block">
&lt;iframe src="view.php?file=yourfile.pdf" 
        width="100%" 
        height="500px"&gt;
&lt;/iframe&gt;
            </div>
            
            <h3>With Custom Path:</h3>
            <div class="code-block">
&lt;iframe src="view.php?file=document.pdf&amp;path=documents/" 
        width="100%" 
        height="600px"&gt;
&lt;/iframe&gt;
            </div>
            
            <h3>Multiple Sizes:</h3>
            <div class="code-block">
&lt;!-- Large --&gt;
&lt;iframe src="view.php?file=report.pdf" height="600px"&gt;&lt;/iframe&gt;

&lt;!-- Medium --&gt;
&lt;iframe src="view.php?file=report.pdf" height="400px"&gt;&lt;/iframe&gt;

&lt;!-- Small --&gt;
&lt;iframe src="view.php?file=report.pdf" height="300px"&gt;&lt;/iframe&gt;
            </div>
        </div>
        
        <div class="section">
            <h2>Live Demos</h2>
            <div class="demo-grid">
                <div class="demo-card">
                    <h3>Large Viewer</h3>
                    <div class="embed-container">
                        <iframe src="view.php?file=test.pdf" 
                                height="300px">
                        </iframe>
                    </div>
                    <p>Full-featured viewer with controls</p>
                </div>
                
                <div class="demo-card">
                    <h3>Medium Viewer</h3>
                    <div class="embed-container">
                        <iframe src="view.php?file=test.pdf" 
                                height="200px">
                        </iframe>
                    </div>
                    <p>Compact view for sidebars</p>
                </div>
                
                <div class="demo-card">
                    <h3>Small Viewer</h3>
                    <div class="embed-container">
                        <iframe src="view.php?file=test.pdf" 
                                height="150px">
                        </iframe>
                    </div>
                    <p>Thumbnail/preview view</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Embed controller functions
        let currentSize = 'large';
        let currentPDF = 'test.pdf';
        
        function changePDF(filename) {
            currentPDF = filename;
            const iframe = document.getElementById('pdfEmbed');
            iframe.src = `view.php?file=${encodeURIComponent(filename)}`;
        }
        
        function changeSize(size) {
            currentSize = size;
            
            // Update active button
            document.querySelectorAll('.size-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Update iframe class
            const iframe = document.getElementById('pdfEmbed');
            iframe.className = `iframe-${size}`;
        }
        
        function reloadViewer() {
            const iframe = document.getElementById('pdfEmbed');
            iframe.src = iframe.src;
        }
        
        function openInNewTab() {
            window.open(`view.php?file=${encodeURIComponent(currentPDF)}`, '_blank');
        }
        
        function toggleFullscreen() {
            const iframe = document.getElementById('pdfEmbed');
            if (iframe.requestFullscreen) {
                iframe.requestFullscreen();
            } else if (iframe.webkitRequestFullscreen) {
                iframe.webkitRequestFullscreen();
            } else if (iframe.msRequestFullscreen) {
                iframe.msRequestFullscreen();
            }
        }
        
        function showCode() {
            const code = `<iframe src="view.php?file=${currentPDF}" width="100%" height="${getHeightBySize(currentSize)}"></iframe>`;
            alert('Copy this code:\n\n' + code);
        }
        
        function getHeightBySize(size) {
            const sizes = {
                'large': '600px',
                'medium': '400px',
                'small': '300px'
            };
            return sizes[size] || '500px';
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // You could load available PDFs from server here
            // fetch('get-pdfs.php').then(response => response.json()).then(pdfs => {
            //     // populate select options
            // });
        });
    </script>
</body>
</html>