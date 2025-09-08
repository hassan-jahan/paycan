document.addEventListener('DOMContentLoaded', function() {
    // DOM Elements
    const loginForm = document.getElementById('login-form');
    const registerForm = document.getElementById('register-form');
    const mainContent = document.getElementById('main-content');
    const showRegisterLink = document.getElementById('show-register');
    const showLoginLink = document.getElementById('show-login');
    const loginButton = document.getElementById('login-button');
    const registerButton = document.getElementById('register-button');
    const logoutButton = document.getElementById('logout-button');
    const saveUrlButton = document.getElementById('save-url');
    const currentUrlInput = document.getElementById('current-url');
    const urlTitleInput = document.getElementById('url-title');
    const savedUrlsList = document.getElementById('saved-urls-list');
    const loginError = document.getElementById('login-error');
    const registerError = document.getElementById('register-error');
    const saveSuccess = document.getElementById('save-success');

    // API Endpoints
    const API_BASE_URL = 'http://localhost:8000/api';
    const LOGIN_ENDPOINT = `${API_BASE_URL}/auth/login`;
    const REGISTER_ENDPOINT = `${API_BASE_URL}/auth/register`;
    const URLS_ENDPOINT = `${API_BASE_URL}/urls`;

    // Check authentication status on load
    checkAuthStatus();

    // Get current tab URL
    getCurrentTabUrl();

    // Event Listeners
    showRegisterLink.addEventListener('click', function(e) {
        e.preventDefault();
        loginForm.style.display = 'none';
        registerForm.style.display = 'block';
    });

    showLoginLink.addEventListener('click', function(e) {
        e.preventDefault();
        registerForm.style.display = 'none';
        loginForm.style.display = 'block';
    });

    loginButton.addEventListener('click', handleLogin);
    registerButton.addEventListener('click', handleRegister);
    logoutButton.addEventListener('click', handleLogout);
    saveUrlButton.addEventListener('click', handleSaveUrl);

    // Functions
    function checkAuthStatus() {
        chrome.storage.local.get(['authToken', 'user'], function(result) {
            if (result.authToken) {
                // User is authenticated
                showAuthenticatedUI(result.user);
                loadSavedUrls();
            } else {
                // User is not authenticated
                showUnauthenticatedUI();
            }
        });
    }

    function getCurrentTabUrl() {
        chrome.tabs.query({active: true, currentWindow: true}, function(tabs) {
            if (tabs && tabs[0]) {
                currentUrlInput.value = tabs[0].url;
            }
        });
    }

    function showAuthenticatedUI(user) {
        loginForm.style.display = 'none';
        registerForm.style.display = 'none';
        mainContent.style.display = 'block';
    }

    function showUnauthenticatedUI() {
        loginForm.style.display = 'block';
        registerForm.style.display = 'none';
        mainContent.style.display = 'none';
    }

    async function handleLogin() {
        const email = document.getElementById('email').value;
        const password = document.getElementById('password').value;

        if (!email || !password) {
            loginError.textContent = 'Please enter both email and password';
            return;
        }

        loginButton.disabled = true;
        loginButton.textContent = 'Logging in...';
        loginError.textContent = '';

        try {
            const response = await fetch(LOGIN_ENDPOINT, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ email, password })
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'Login failed');
            }

            // Save auth token and user data
            chrome.storage.local.set({
                authToken: data.token,
                user: data.user
            }, function() {
                // Show authenticated UI
                showAuthenticatedUI(data.user);
                loadSavedUrls();
            });

            // Send message to background script
            chrome.runtime.sendMessage({
                action: 'login',
                token: data.token,
                user: data.user
            });

        } catch (error) {
            loginError.textContent = error.message || 'Login failed. Please try again.';
        } finally {
            loginButton.disabled = false;
            loginButton.textContent = 'Login';
        }
    }

    async function handleRegister() {
        const name = document.getElementById('reg-name').value;
        const email = document.getElementById('reg-email').value;
        const password = document.getElementById('reg-password').value;
        const passwordConfirm = document.getElementById('reg-password-confirm').value;

        if (!name || !email || !password || !passwordConfirm) {
            registerError.textContent = 'Please fill in all fields';
            return;
        }

        if (password !== passwordConfirm) {
            registerError.textContent = 'Passwords do not match';
            return;
        }

        registerButton.disabled = true;
        registerButton.textContent = 'Registering...';
        registerError.textContent = '';

        try {
            const response = await fetch(REGISTER_ENDPOINT, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    name,
                    email,
                    password,
                    password_confirmation: passwordConfirm
                })
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'Registration failed');
            }

            // Save auth token and user data
            chrome.storage.local.set({
                authToken: data.token,
                user: data.user
            }, function() {
                // Show authenticated UI
                showAuthenticatedUI(data.user);
            });

            // Send message to background script
            chrome.runtime.sendMessage({
                action: 'login',
                token: data.token,
                user: data.user
            });

        } catch (error) {
            registerError.textContent = error.message || 'Registration failed. Please try again.';
        } finally {
            registerButton.disabled = false;
            registerButton.textContent = 'Register';
        }
    }

    function handleLogout() {
        // Clear auth data
        chrome.storage.local.remove(['authToken', 'user'], function() {
            // Show unauthenticated UI
            showUnauthenticatedUI();
        });

        // Send message to background script
        chrome.runtime.sendMessage({ action: 'logout' });
    }

    async function handleSaveUrl() {
        const url = currentUrlInput.value;
        const title = urlTitleInput.value;

        if (!url) {
            saveSuccess.textContent = 'No URL to save';
            saveSuccess.style.color = '#e53e3e';
            return;
        }

        if (!title) {
            saveSuccess.textContent = 'Please enter a title for this URL';
            saveSuccess.style.color = '#e53e3e';
            return;
        }

        saveUrlButton.disabled = true;
        saveUrlButton.textContent = 'Saving...';
        saveSuccess.textContent = '';

        try {
            const token = await getAuthToken();
            
            const response = await fetch(URLS_ENDPOINT, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${token}`
                },
                body: JSON.stringify({ url, title })
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'Failed to save URL');
            }

            // Clear title input
            urlTitleInput.value = '';
            
            // Show success message
            saveSuccess.textContent = 'URL saved successfully!';
            saveSuccess.style.color = '#38a169';
            
            // Reload saved URLs
            loadSavedUrls();

        } catch (error) {
            saveSuccess.textContent = error.message || 'Failed to save URL. Please try again.';
            saveSuccess.style.color = '#e53e3e';
        } finally {
            saveUrlButton.disabled = false;
            saveUrlButton.textContent = 'Save URL';
        }
    }

    async function loadSavedUrls() {
        try {
            const token = await getAuthToken();
            
            const response = await fetch(URLS_ENDPOINT, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${token}`
                }
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'Failed to load URLs');
            }

            // Clear current list
            savedUrlsList.innerHTML = '';
            
            // Add URLs to list
            if (data.urls && data.urls.length > 0) {
                data.urls.forEach(url => {
                    const urlItem = document.createElement('div');
                    urlItem.className = 'url-item';
                    urlItem.innerHTML = `
                        <div class="url-title">${url.title}</div>
                        <a href="${url.url}" class="url-link" target="_blank">${url.url}</a>
                        <div class="url-actions">
                            <button class="delete-url" data-id="${url.id}">Delete</button>
                        </div>
                    `;
                    savedUrlsList.appendChild(urlItem);
                    
                    // Add event listener to delete button
                    const deleteButton = urlItem.querySelector('.delete-url');
                    deleteButton.addEventListener('click', function() {
                        deleteUrl(url.id);
                    });
                });
            } else {
                savedUrlsList.innerHTML = '<div class="url-item">No saved URLs yet</div>';
            }

        } catch (error) {
            savedUrlsList.innerHTML = `<div class="url-item">Error loading URLs: ${error.message}</div>`;
        }
    }

    async function deleteUrl(id) {
        try {
            const token = await getAuthToken();
            
            const response = await fetch(`${URLS_ENDPOINT}/${id}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${token}`
                }
            });

            if (!response.ok) {
                const data = await response.json();
                throw new Error(data.message || 'Failed to delete URL');
            }

            // Reload saved URLs
            loadSavedUrls();
            
            // Show success message
            saveSuccess.textContent = 'URL deleted successfully!';
            saveSuccess.style.color = '#38a169';

        } catch (error) {
            saveSuccess.textContent = error.message || 'Failed to delete URL. Please try again.';
            saveSuccess.style.color = '#e53e3e';
        }
    }

    function getAuthToken() {
        return new Promise((resolve, reject) => {
            chrome.storage.local.get('authToken', function(result) {
                if (result.authToken) {
                    resolve(result.authToken);
                } else {
                    reject(new Error('Not authenticated'));
                }
            });
        });
    }
});