<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reddit Scraper & Google Drive Uploader</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: #f9fafc;
        }
        .container {
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            max-width: 400px;
            width: 100%;
        }
        h1 {
            font-size: 1.5rem;
            margin-bottom: 20px;
            text-align: center;
        }
        form {
            display: flex;
            flex-direction: column;
        }
        label {
            margin-bottom: 5px;
            font-weight: bold;
        }
        input, button {
            margin-bottom: 15px;
            padding: 10px;
            font-size: 1rem;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        button {
            background-color: #4CAF50;
            color: white;
            cursor: pointer;
            font-size: 1rem;
        }
        button:hover {
            background-color: #45a049;
        }
        .alert {
            margin-top: 20px;
            padding: 10px;
            border-radius: 4px;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Reddit Scraper & Google Drive Uploader</h1>

        {{-- Success Alert --}}
        @if (session('message'))
            <div class="alert alert-success">
                {{ session('message') }}
            </div>
        @endif

        {{-- Error Alert --}}
        @if (session('error'))
            <div class="alert alert-error">
                {{ session('error') }}
            </div>
        @endif

        {{-- Scraper Form --}}
        <form method="POST" action="/scrape-and-upload" onsubmit="showScrapingModal(event)">
            @csrf
            <label for="reddit_url">Reddit URL:</label>
            <input type="url" id="reddit_url" name="reddit_url" required placeholder="https://www.reddit.com/r/kittens/" />

            <label for="minimum_upvotes">Minimum Upvotes:</label>
            <input type="number" id="minimum_upvotes" name="minimum_upvotes" value="50" min="1" />

            <label for="keywords">Keywords (comma-separated):</label>
            <input type="text" id="keywords" name="keywords" placeholder="e.g., kittens, rescue" />

            <label for="drive_folder_link">Google Drive Folder Link:</label>
            <input type="url" id="drive_folder_link" name="drive_folder_link" required placeholder="Enter Google Drive folder link" />

            <label for="scrape_images">Scrape Images:</label>
            <input type="hidden" name="scrape_images" value="0">
            <input type="checkbox" id="scrape_images" name="scrape_images" value="1" checked />

            <label for="scrape_videos">Scrape Videos:</label>
            <input type="hidden" name="scrape_videos" value="0">
            <input type="checkbox" id="scrape_videos" name="scrape_videos" value="1" checked />

            <label for="create_folders">Automatically Create Folders Per Reddit Thread:</label>
            <input type="hidden" name="create_folders" value="0">
            <input type="checkbox" id="create_folders" name="create_folders" value="1" />

            <button type="submit">Start Scraping</button>
        </form>

        {{-- Scraping Modal --}}
        <div id="scrapingModal" style="display:none;">
            <h2>Scraping...</h2>
            <p>File Size: <span id="fileSize">0</span> MB</p>
            <button onclick="pauseScraping()">Pause</button>
            <button onclick="stopScraping()">Stop</button>
        </div>

        <script>
            let isPaused = false;
            let isStopped = false;
            let totalFileSize = 0;
            const csrfToken = '{{ csrf_token() }}';

            function showScrapingModal(event) {
                event.preventDefault();
                isPaused = false;
                isStopped = false;
                totalFileSize = 0;
                document.getElementById('scrapingModal').style.display = 'block';

                // Submit the form programmatically
                const formData = new FormData(event.target);
                fetch('/scrape-and-upload', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': csrfToken
                    }
                }).then(response => response.json())
                  .then(data => {
                      alert('Scraping completed successfully!');
                      document.getElementById('scrapingModal').style.display = 'none';
                  }).catch(error => {
                      console.error('Error during scraping:', error);
                      alert('An error occurred during scraping.');
                      document.getElementById('scrapingModal').style.display = 'none';
                  });
            }

            function pauseScraping() {
                fetch('/scraper/pause', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken
                    }
                }).then(() => {
                    isPaused = true;
                    alert("Scraping paused.");
                }).catch(error => {
                    console.error('Error pausing scraping:', error);
                    alert('Failed to pause scraping.');
                });
            }

            function stopScraping() {
                fetch('/scraper/stop', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken
                    }
                }).then(() => {
                    isStopped = true;
                    alert("Scraping stopped.");
                    document.getElementById('scrapingModal').style.display = 'none';
                }).catch(error => {
                    console.error('Error stopping scraping:', error);
                    alert('Failed to stop scraping.');
                });
            }

            function updateFileSize(size) {
                totalFileSize += size;
                document.getElementById('fileSize').textContent = (totalFileSize / (1024 * 1024)).toFixed(2) + " MB";
            }
        </script>

    </div>
</body>
</html>
