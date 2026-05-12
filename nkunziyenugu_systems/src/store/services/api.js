import axios from "axios";

// API base URL is environment-driven so the same code ships to dev and prod.
// .env.local      -> http://192.168.x.x:8000/api  (dev LAN, Vue CLI :8080 -> Laravel :8000)
// .env.production -> /api                          (prod, same-origin under nkunziyenungu.co.za)
const api = axios.create({
  baseURL: process.env.VUE_APP_API_BASE_URL || "/api",
  headers: {
    "Content-Type": "application/json",
    "Accept": "application/json"
  }
});

// Attach token and active account ID
api.interceptors.request.use(config => {
  const token = localStorage.getItem("token");
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  const activeAccount = JSON.parse(localStorage.getItem("activeAccount") || "null");

  // Only attach account header if it exists
  if (activeAccount?.id) {
    config.headers["X-Account-ID"] = activeAccount.id;
  } else {
    delete config.headers["X-Account-ID"];
  }

  // Let Axios set Content-Type automatically for FormData (includes multipart boundary)
  if (config.data instanceof FormData) {
    delete config.headers["Content-Type"];
  }

  return config;
});


// Handle 401 globally (EXCEPT auth routes)
api.interceptors.response.use(
  response => response,
  error => {
    const status = error.response?.status;
    const url = error.config?.url || "";

    const authRoutes = [
      "/login",
      "/register",
      "/forgot-password",
      "/reset-password"
    ];

    const isAuthRoute = authRoutes.some(route => url.includes(route));

    // Only logout on REAL authentication failure
    if (status === 401 && !isAuthRoute) {
      localStorage.removeItem("token");
      localStorage.removeItem("user");
      localStorage.removeItem("accounts");
      localStorage.removeItem("activeAccount");
      localStorage.removeItem("expires_at");

      window.location.href = "/LogIn";
      return;
    }

    //  Permission denied → stay logged in
    if (status === 403) {
      console.warn("Permission denied:", error.response?.data?.message);
    }

    return Promise.reject(error);
  }
);


export default api;
