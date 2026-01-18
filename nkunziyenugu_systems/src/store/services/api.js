import axios from "axios";

const api = axios.create({
  baseURL: "http://127.0.0.1:8000/api",
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
  console.log("Active Account:", activeAccount);
  if (activeAccount?.id) {
    config.headers["X-Account-ID"] = activeAccount.id;
  } else {
    delete config.headers["X-Account-ID"];
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
