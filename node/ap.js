const express = require('express');
const puppeteer = require('puppeteer');
const Tesseract = require('tesseract.js');
const cors = require('cors');
const express = require("express");
const multer = require("multer");
const tf = require("@tensorflow/tfjs-node");
const mobilenet = require("@tensorflow-models/mobilenet");
const Jimp = require("jimp");
const path = require("path");

// Initialize express app
const app = express();
const PORT = process.env.PORT || 3000;

// Middleware
app.use(express.json());
app.use(cors());
const upload = multer({ storage: multer.memoryStorage() });

let model;

// Load MobileNet model
async function loadModel() {
  model = await mobilenet.load({ version: 2, alpha: 1.0 });
  console.log("MobileNetV2 model loaded.");
}

app.get("/", (req, res) => {
  res.send("Image Recognition API is running.");
});

app.post("/recognize_image", upload.single("image"), async (req, res) => {
  if (!req.file) {
    return res.status(400).json({ error: "No image file provided" });
  }

  try {
    // Load and preprocess the image with Jimp
    const imageBuffer = req.file.buffer;
    const jimpImage = await Jimp.read(imageBuffer);
    jimpImage.resize(224, 224);
    const imageData = new Uint8Array(jimpImage.bitmap.data);

    const input = tf.node.decodeImage(imageData, 3)
      .expandDims(0)
      .toFloat()
      .div(tf.scalar(127.5))
      .sub(tf.scalar(1));

    // Predict using MobileNetV2
    const predictions = await model.classify(input);
    res.json({ predictions });

  } catch (err) {
    console.error(err);
    res.status(500).json({ error: err.message });
  }
});

// API endpoint to get prices for a search term
app.get('/api/prices', async (req, res) => {
  const searchTerm = req.query.search;
  
  if (!searchTerm) {
    return res.status(400).json({ error: 'Search term is required' });
  }
  
  try {
    const priceData = await getScreenshotAndExtractPrices(searchTerm);
    res.json(priceData);
  } catch (error) {
    console.error('Error processing request:', error);
    res.status(500).json({ error: 'Failed to process request', message: error.message });
  }
});

// Function to scroll and take screenshot
async function autoScroll(page) {
  await page.evaluate(async () => {
    await new Promise((resolve) => {
      let totalHeight = 0;
      const distance = 400;
      const timer = setInterval(() => {
        window.scrollBy(0, distance);
        totalHeight += distance;

        if (totalHeight >= document.body.scrollHeight - window.innerHeight) {
          clearInterval(timer);
          resolve();
        }
      }, 200);
    });
  });
}

// Main function to get screenshot and extract prices
async function getScreenshotAndExtractPrices(searchTerm) {
  const browser = await puppeteer.launch({ 
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox']
  });
  
  const page = await browser.newPage();
  
  try {
    // Navigate to search results page
    const encodedQuery = encodeURIComponent(searchTerm);
    const url = `https://www.takealot.com/all?qsearch=${encodedQuery}`;
    await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 60000 });

    // Accept cookie if needed
    try {
      await page.waitForSelector('button[class*="cookie"]', { timeout: 3000 });
      await page.click('button[class*="cookie"]');
    } catch (error) {
      // Continue if cookie banner doesn't appear
    }

    // Scroll to load more products
    await autoScroll(page);

    // Take screenshot as buffer (in memory) instead of saving to disk
    const screenshotBuffer = await page.screenshot({ fullPage: true });
    
    // Process the screenshot buffer directly with Tesseract
    const result = await Tesseract.recognize(screenshotBuffer, 'eng');
    const rawText = result.data.text;

    // Parse R prices from text
    const priceMatches = [...rawText.matchAll(/R\s?(\d{1,3}(,\d{3})*(\.\d{1,2})?)/g)];
    
    // Clean up and convert to numbers
    const prices = priceMatches.map(match => {
      // Remove commas and convert to float
      return parseFloat(match[1].replace(/,/g, ''));
    }).filter(price => !isNaN(price) && price > 0);

    // Calculate statistics
    const stats = calculatePriceStats(prices);

    return {
      searchTerm,
      timestamp: new Date().toISOString(),
      results: {
        totalPricesFound: prices.length,
        prices: prices,
        stats
      }
    };
  } catch (error) {
    throw error;
  } finally {
    await browser.close();
  }
}

// Calculate price statistics
function calculatePriceStats(prices) {
  if (prices.length === 0) {
    return {
      average: 0,
      median: 0,
      min: 0,
      max: 0
    };
  }

  const sortedPrices = [...prices].sort((a, b) => a - b);
  
  const sum = prices.reduce((acc, price) => acc + price, 0);
  const average = sum / prices.length;
  
  let median;
  const mid = Math.floor(sortedPrices.length / 2);
  if (sortedPrices.length % 2 === 0) {
    median = (sortedPrices[mid - 1] + sortedPrices[mid]) / 2;
  } else {
    median = sortedPrices[mid];
  }

  return {
    average: parseFloat(average.toFixed(2)),
    median: parseFloat(median.toFixed(2)),
    min: sortedPrices[0],
    max: sortedPrices[sortedPrices.length - 1],
    count: prices.length
  };
}

// Start server
app.listen(PORT, () => {
  console.log(`🚀 Takealot Price API is running on port ${PORT}`);
  console.log(`📊 Try it out: http://localhost:${PORT}/api/prices?search=phone`);
});

module.exports = app; 