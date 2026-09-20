/**
 * auth.js — JWT token & user helpers for frontend pages
 * Stores token and user info in localStorage.
 */

const Auth = (() => {
    const TOKEN_KEY = 'atomix_token';
    const USER_KEY  = 'atomix_user';

    return {
        getToken() {
            return localStorage.getItem(TOKEN_KEY);
        },

        setToken(token) {
            localStorage.setItem(TOKEN_KEY, token);
        },

        getUser() {
            try {
                return JSON.parse(localStorage.getItem(USER_KEY));
            } catch {
                return null;
            }
        },

        setUser(user) {
            localStorage.setItem(USER_KEY, JSON.stringify(user));
        },

        clear() {
            localStorage.removeItem(TOKEN_KEY);
            localStorage.removeItem(USER_KEY);
        },

        isLoggedIn() {
            return !!this.getToken();
        },

        /**
         * Redirect to login if not authenticated.
         * Call at the top of every protected page.
         */
        requireAuth(loginUrl = 'login.html') {
            if (!this.getToken()) {
                window.location.href = loginUrl;
            }
        },

        /**
         * Redirect away from login page if already authenticated.
         */
        redirectIfAuthenticated(dashboardUrl = 'dashboard.html') {
            if (this.getToken()) {
                window.location.href = dashboardUrl;
            }
        },

        async logout(apiBase = '../../api') {
            this.clear();
            window.location.href = 'login.html';
        }
    };
})();
