import axios from "axios";

const api = axios.create({
  baseURL: "http://127.0.0.1:8000/api",
  headers: {
    "Content-Type": "application/json",
    "Accept": "application/json"
  }
});

// Attach token
api.interceptors.request.use(config => {
  const token = localStorage.getItem("token");
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
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

    if (status === 401 && !isAuthRoute) { // Unauthorized
      localStorage.removeItem("token"); 
      localStorage.removeItem("user");
      window.location.href = "/LogIn";
    }

    return Promise.reject(error);
  }
);

export default api;
