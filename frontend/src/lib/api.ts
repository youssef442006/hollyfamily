import axios from 'axios';

const API_BASE = process.env.NEXT_PUBLIC_API_URL || '';

const api = axios.create({
  baseURL: API_BASE,
  withCredentials: true,
  headers: { 'Content-Type': 'application/json', 'ngrok-skip-browser-warning': 'true' },
});

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      if (typeof window !== 'undefined' && window.location.pathname !== '/login') {
        window.location.href = '/login';
      }
    }
    return Promise.reject(error);
  }
);

export default api;

export const authAPI = {
  login: (username: string, password: string) => {
    const formData = new URLSearchParams();
    formData.append('username', username);
    formData.append('password', password);
    return api.post('/api/auth/login', formData, {
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    });
  },
  logout: () => api.post('/api/auth/logout'),
  register: (data: any) => api.post('/api/auth/register', data),
  getMe: () => api.get('/api/auth/me'),
  getUsers: () => api.get('/api/auth/users'),
};

export const familiesAPI = {
  list: (params?: any) => api.get('/api/families', { params }),
  get: (id: number) => api.get(`/api/families/${id}`),
  create: (data: any) => api.post('/api/families', data),
  update: (id: number, data: any) => api.put(`/api/families/${id}`, data),
  delete: (id: number) => api.delete(`/api/families/${id}`),
  getMembers: (id: number) => api.get(`/api/families/${id}/members`),
  addMember: (id: number, data: any) => api.post(`/api/families/${id}/members`, data),
  updateMember: (familyId: number, personId: number, data: any) => api.put(`/api/families/${familyId}/members/${personId}`, data),
  removeMember: (familyId: number, personId: number) => api.delete(`/api/families/${familyId}/members/${personId}`),
  endMember: (familyId: number, personId: number) => api.post(`/api/families/${familyId}/members/end/${personId}`),
  wizard: (id: number, data: any) => api.post(`/api/families/${id}/members/wizard`, data),
  createComplete: (data: any) => api.post('/api/families/create-complete', data),
  checkDuplicate: (data: any) => api.post('/api/families/check-duplicate', data),
  getTree: (id: number) => api.get(`/api/families/${id}/tree`),
};

export const personsAPI = {
  list: (params?: any) => api.get('/api/persons', { params }),
  get: (id: number) => api.get(`/api/persons/${id}`),
  create: (data: any) => api.post('/api/persons', data),
  update: (id: number, data: any) => api.put(`/api/persons/${id}`, data),
  deletePerson: (id: number) => api.delete(`/api/persons/${id}`),
  checkDuplicate: (data: any) => api.post('/api/persons/check-duplicate', data),
  checkPhone: (data: any) => api.post('/api/persons/check-phone', data),
  listDuplicates: () => api.get('/api/persons/duplicates'),
  getProfile: (id: number) => api.get(`/api/persons/${id}/profile`),
  merge: (primaryId: number, duplicateId: number) =>
    api.post(`/api/persons/merge?primary_person_id=${primaryId}&duplicate_person_id=${duplicateId}`),
  changeService: (id: number, serviceId?: number) =>
    api.post(`/api/persons/${id}/change-service${serviceId ? `?service_id=${serviceId}` : ''}`),
  getRelationships: (id: number) => api.get(`/api/persons/${id}/relationships`),
  createRelationship: (id: number, data: any) => api.post(`/api/persons/${id}/relationships`, data),
  deleteRelationship: (id: number, relId: number) => api.delete(`/api/persons/${id}/relationships/${relId}`),
  getFamilyHistory: (id: number) => api.get(`/api/persons/${id}/family-history`),
  getTree: (id: number) => api.get(`/api/persons/${id}/tree`),
  createFamily: (id: number, data: any) => api.post(`/api/persons/${id}/create-family`, data),
  promoteStages: () => api.post('/api/persons/promote-stages'),
};

export const servicesAPI = {
  list: (params?: any) => api.get('/api/services', { params }),
  get: (id: number) => api.get(`/api/services/${id}`),
  create: (data: any) => api.post('/api/services', data),
  update: (id: number, data: any) => api.put(`/api/services/${id}`, data),
  deleteService: (id: number) => api.delete(`/api/services/${id}`),
  getMembers: (id: number) => api.get(`/api/services/${id}/members`),
  addMember: (id: number, data: any) => api.post(`/api/services/${id}/members`, data),
  getAssignments: (id: number) => api.get(`/api/services/${id}/assignments`),
  createAssignment: (id: number, data: any) => api.post(`/api/services/${id}/assignments`, data),
  autoAssign: (serviceId: number, personId: number) =>
    api.post(`/api/services/${serviceId}/auto-assign?person_id=${personId}`),
};

export const visitsAPI = {
  list: (params?: any) => api.get('/api/visits', { params }),
  get: (id: number) => api.get(`/api/visits/${id}`),
  create: (data: any) => api.post('/api/visits', data),
  update: (id: number, data: any) => api.put(`/api/visits/${id}`, data),
  delete: (id: number) => api.delete(`/api/visits/${id}`),
};

export const confessionsAPI = {
  list: (params?: any) => api.get('/api/confessions', { params }),
  get: (id: number) => api.get(`/api/confessions/${id}`),
  create: (data: any) => api.post('/api/confessions', data),
  update: (id: number, data: any) => api.put(`/api/confessions/${id}`, data),
  complete: (id: number) => api.post(`/api/confessions/${id}/complete`),
  delete: (id: number) => api.delete(`/api/confessions/${id}`),
};

export const dashboardAPI = {
  get: () => api.get('/api/dashboard'),
};

export const searchAPI = {
  search: (q: string) => api.get('/api/search', { params: { q } }),
};

export const auditAPI = {
  list: (params?: any) => api.get('/api/audit', { params }),
};

export const priestsAPI = {
  list: () => api.get('/api/priests'),
  get: (id: number) => api.get(`/api/priests/${id}`),
  create: (data: any) => api.post('/api/priests', data),
  update: (id: number, data: any) => api.put(`/api/priests/${id}`, data),
};

export const settingsAPI = {
  list: () => api.get('/api/settings'),
  get: (key: string) => api.get(`/api/settings/${key}`),
  create: (data: any) => api.post('/api/settings', data),
  update: (key: string, data: any) => api.put(`/api/settings/${key}`, data),
  clearData: () => api.post('/api/settings/clear'),
  exportData: () => api.get('/api/settings/export', { responseType: 'blob' }),
  sampleTemplate: () => api.get('/api/settings/sample', { responseType: 'blob' }),
  importData: (file: File) => {
    const fd = new FormData();
    fd.append('file', file);
    return api.post('/api/settings/import', fd, { headers: { 'Content-Type': 'multipart/form-data' } });
  },
};

export const reportsAPI = {
  families: () => api.get('/api/reports/families'),
  services: () => api.get('/api/reports/services'),
  ageDistribution: () => api.get('/api/reports/age-distribution'),
  ageDistributionByYear: () => api.get('/api/reports/age-distribution/by-year'),
  neglected: (days?: number) => api.get('/api/reports/neglected', { params: { days } }),
  withoutService: () => api.get('/api/reports/without-service'),
  specialCases: () => api.get('/api/reports/special-cases'),
  exportCSV: (type: string) => api.get(`/api/reports/export/csv/${type}`, { responseType: 'blob' }),
};
