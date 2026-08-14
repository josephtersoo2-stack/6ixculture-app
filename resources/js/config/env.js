const ENV = {
    API_URL: import.meta.env.VITE_HOST || (typeof APP_URL !== 'undefined' ? APP_URL : ''),
    DEMO: import.meta.env.VITE_DEMO || (typeof APP_DEMO !== 'undefined' ? APP_DEMO : false),
    API_KEY: import.meta.env.VITE_API_KEY || (typeof APP_KEY !== 'undefined' ? APP_KEY : '')
};
export default ENV;
