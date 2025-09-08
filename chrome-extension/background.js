// Background script for Larav12 Chrome Extension

// Listen for messages from popup.js
chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
    if (message.action === 'login') {
        // Store auth token and user data
        chrome.storage.local.set({
            authToken: message.token,
            user: message.user
        });
        console.log('User logged in:', message.user.name);
    } else if (message.action === 'logout') {
        // Clear auth data
        chrome.storage.local.remove(['authToken', 'user']);
        console.log('User logged out');
    }
    return true;
});

// Initialize extension when installed or updated
chrome.runtime.onInstalled.addListener((details) => {
    console.log('Larav12 Extension installed/updated:', details.reason);
    
    // Clear any existing auth data on install/update
    if (details.reason === 'install') {
        chrome.storage.local.remove(['authToken', 'user']);
    }
});

// Listen for storage changes
chrome.storage.onChanged.addListener((changes, namespace) => {
    if (namespace === 'local') {
        if (changes.authToken) {
            console.log('Auth token changed:', changes.authToken.newValue ? 'Token set' : 'Token removed');
        }
    }
});