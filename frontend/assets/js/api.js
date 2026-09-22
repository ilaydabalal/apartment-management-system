
// API Base URL
const API_BASE_URL = 'http://localhost/apartment-management/api';


async function api(endpoint, method = 'GET', data = null, options = {}) {
    const url = `${API_BASE_URL}${endpoint}`;
    
    console.log('API İsteği:', method, url, data);
    
    const config = {
        method: method.toUpperCase(),
        headers: {
            'Content-Type': 'application/json',
            ...options.headers
        },
        ...options
    };
    
    const token = getAuthToken();
    if (token) {
        config.headers['Authorization'] = `Bearer ${token}`;
    }
    
    if (data && ['POST', 'PUT', 'DELETE'].includes(config.method)) {
        config.body = JSON.stringify(data);
    }
    
    try {
        const response = await fetch(url, config);
        const text = await response.text();
        
        console.log('API Yanıtı:', text);
        
        // Token süresi dolmuşsa login'e yönlendir
        if (response.status === 401) {
            handleAuthError();
            throw new Error('Oturum süresi doldu');
        }
        
        try {
            const result = JSON.parse(text);
            return result;
        } catch (parseError) {
            console.error('JSON Parse Hatası:', text);
            throw new Error('Geçersiz JSON yanıtı: ' + text.substring(0, 100));
        }
    } catch (error) {
        console.error('API Hatası:', error);
        throw error;
    }
}


function getAuthToken() {
    return localStorage.getItem('auth_token') || sessionStorage.getItem('auth_token');
}

function handleAuthError() {
    localStorage.removeItem('auth_token');
    localStorage.removeItem('user_data');
    sessionStorage.removeItem('auth_token');
    sessionStorage.removeItem('user_data');
    
    if (window.location.pathname !== '/index.html' && window.location.pathname !== '/') {
        window.location.href = 'index.html';
    }
}

function getCurrentUser() {
    const userData = localStorage.getItem('user_data') || sessionStorage.getItem('user_data');
    return userData ? JSON.parse(userData) : null;
}

async function logout() {
    try {
        await api('/auth/logout.php', 'POST');
    } catch (error) {
        console.error('Logout error:', error);
    } finally {
        localStorage.removeItem('auth_token');
        localStorage.removeItem('user_data');
        sessionStorage.removeItem('auth_token');
        sessionStorage.removeItem('user_data');
        window.location.href = 'index.html';
    }
}

function hasPermission(module, action) {
    const user = getCurrentUser();
    if (!user) return false;
    
    if (user.role_name === 'Süper Admin') return true;
    
    if (!user.permissions || !user.permissions[module]) return false;
    
    return user.permissions[module].includes(action);
}

function showIfPermitted(elementId, module, action) {
    const element = document.getElementById(elementId);
    if (element) {
        element.style.display = hasPermission(module, action) ? 'block' : 'none';
    }
}

function showToast(message, type = 'info', duration = 3000) {
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        `;
        document.body.appendChild(toastContainer);
    }
    
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.style.cssText = `
        background: ${type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : '#17a2b8'};
        color: white;
        padding: 12px 20px;
        border-radius: 5px;
        margin-bottom: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        transform: translateX(100%);
        transition: transform 0.3s ease;
    `;
    toast.textContent = message;
    
    toastContainer.appendChild(toast);
    
    setTimeout(() => {
        toast.style.transform = 'translateX(0)';
    }, 100);
    
    setTimeout(() => {
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    }, duration);
}


const UserAPI = {
    async getUsers(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        return await api(`/users/kullanicilar.php?${queryString}`);
    },
    
    async getUser(id) {
        return await api(`/users/kullanicilar.php?id=${id}`);
    },
    
    async createUser(userData) {
        return await api('/users/kullanicilar.php', 'POST', userData);
    },
    
    async updateUser(userData) {
        return await api('/users/kullanicilar.php', 'PUT', userData);
    },
    
    async deleteUser(id) {
        return await api('/users/kullanicilar.php', 'DELETE', { id });
    }
};

// Aidat API'leri
const DueAPI = {
    async getDues(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        return await api(`/dues/aidatlar.php?${queryString}`);
    },
    
    async getDue(id) {
        return await api(`/dues/aidatlar.php?id=${id}`);
    },
    
    async createDues(dueData) {
        return await api('/dues/aidatlar.php', 'POST', dueData);
    },
    
    async updateDue(dueData) {
        return await api('/dues/aidatlar.php', 'PUT', dueData);
    },
    
    async deleteDue(id) {
        return await api('/dues/aidatlar.php', 'DELETE', { id });
    }
};

// Apartman API'leri
const ApartmentAPI = {
    async getApartments(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        return await api(`/apartments/apartmanlar.php?${queryString}`);
    },
    
    async getApartment(id) {
        return await api(`/apartments/apartmanlar.php?id=${id}`);
    },
    
    async createApartment(apartmentData) {
        return await api('/apartments/apartmanlar.php', 'POST', apartmentData);
    },
    
    async updateApartment(apartmentData) {
        return await api('/apartments/apartmanlar.php', 'PUT', apartmentData);
    },
    
    async deleteApartment(id) {
        return await api('/apartments/apartmanlar.php', 'DELETE', { id });
    }
};

// Daire API'leri
const UnitAPI = {
    async getUnits(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        return await api(`/apartments/daireler.php?${queryString}`);
    },
    
    async getUnit(id) {
        return await api(`/apartments/daireler.php?id=${id}`);
    },
    
    async createUnit(unitData) {
        return await api('/apartments/daireler.php', 'POST', unitData);
    },
    
    async updateUnit(unitData) {
        return await api('/apartments/daireler.php', 'PUT', unitData);
    },
    
    async deleteUnit(id) {
        return await api('/apartments/daireler.php', 'DELETE', { id });
    }
};

// Mali işlemler API'leri
const FinanceAPI = {
    async getIncomes(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        return await api(`/finance/gelirler.php?${queryString}`);
    },
    
    async getExpenses(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        return await api(`/finance/giderler.php?${queryString}`);
    },
    
    async getReports(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        return await api(`/finance/financial_report.php?${queryString}`);    },
    
    async createIncome(incomeData) {
        return await api('/finance/gelirler.php', 'POST', incomeData);
    },
    
    async createExpense(expenseData) {
        return await api('/finance/giderler.php', 'POST', expenseData);
    }
};

// Rol API'leri 
const RoleAPI = {
    async getRoles(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const endpoint = queryString ? `/users/roles.php?${queryString}` : '/users/roles.php';
        return await api(endpoint);
    },
    
    async getRole(id) {
        return await api(`/users/roles.php?id=${id}`);
    },
    
    async createRole(roleData) {
        return await api('/users/roles.php', 'POST', roleData);
    },
    
    async updateRole(roleData) {
        return await api('/users/roles.php', 'PUT', roleData);
    },
    
    async deleteRole(id) {
        return await api('/users/roles.php', 'DELETE', { id });
    }
};

// Gelir Kategorileri API'si
const IncomeCategoryAPI = {
    async getCategories() {
        return await api('/finance/income_categories.php');
    }
};

// Debug için
console.log('API dosyası yüklendi');
console.log('RoleAPI tanımlı:', typeof RoleAPI);

window.addEventListener('load', function() {
    const token = getAuthToken();
    const currentPage = window.location.pathname;
    
    if (!token && !currentPage.includes('index.html') && currentPage !== '/') {
        console.log('Token yok, login sayfasına yönlendiriliyor');
    }
});

window.addEventListener('unhandledrejection', function(event) {
    console.error('Unhandled promise rejection:', event.reason);
    if (event.reason.message?.includes('token') || event.reason.message?.includes('401')) {
        handleAuthError();
    }
});

const ExpenseAPI = {
    async getPaymentDetails(firma) {
        try {
            const encodedFirma = encodeURIComponent(firma);
            const response = await fetch(`api/get_payment_details.php?firma=${encodedFirma}`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            console.log('Payment details response:', result);
            return result;
        } catch (error) {
            console.error('ExpenseAPI.getPaymentDetails error:', error);
            return { success: false, message: error.message };
        }
    },
    
    exportPayments(firma) {
        try {
            const encodedFirma = encodeURIComponent(firma);
            window.open(`export_payments.php?firma=${encodedFirma}`, '_blank');
        } catch (error) {
            console.error('ExpenseAPI.exportPayments error:', error);
        }
    }
};

if (typeof DuesAPI === 'undefined') {
    window.DuesAPI = {
        async getDues(params = {}) {
            try {
                const queryString = new URLSearchParams(params).toString();
                const response = await apiRequest('GET', `/apartment-management/api/dues/aidatlar.php?${queryString}`);
                return response;
            } catch (error) {
                console.error('DuesAPI.getDues error:', error);
                return { success: false, message: error.message, data: { dues: [] } };
            }
        },
        
        async getDue(id) {
            try {
                return await apiRequest('GET', `/apartment-management/api/dues/dues_management.php?id=${id}`);
            } catch (error) {
                console.error('DuesAPI.getDue error:', error);
                return { success: false, message: error.message };
            }
        },
        
        async updateDue(data) {
            try {
                return await apiRequest('PUT', '/apartment-management/api/dues/dues_management.php', data);
            } catch (error) {
                console.error('DuesAPI.updateDue error:', error);
                return { success: false, message: error.message };
            }
        },
        
        async deleteDue(id) {
            try {
                return await apiRequest('DELETE', `/apartment-management/api/dues/dues_management.php?id=${id}`);
            } catch (error) {
                console.error('DuesAPI.deleteDue error:', error);
                return { success: false, message: error.message };
            }
        },
        
        async generateDues(data) {
            try {
                return await apiRequest('POST', '/apartment-management/api/dues/generate_dues.php', data);
            } catch (error) {
                console.error('DuesAPI.generateDues error:', error);
                return { success: false, message: error.message };
            }
        }
    };
}

if (typeof FinanceAPI === 'undefined') {
    window.FinanceAPI = {
        async getIncomes(params = {}) {
            try {
                const queryString = new URLSearchParams(params).toString();
                return await apiRequest('GET', `/apartment-management/api/finance/gelirler.php?${queryString}`);
            } catch (error) {
                console.error('FinanceAPI.getIncomes error:', error);
                return { success: false, message: error.message };
            }
        },
        
        async getExpenses(params = {}) {
            try {
                const queryString = new URLSearchParams(params).toString();
                return await apiRequest('GET', `/apartment-management/api/finance/giderler.php?${queryString}`);
            } catch (error) {
                console.error('FinanceAPI.getExpenses error:', error);
                return { success: false, message: error.message };
            }
        },
        
        async getReports(params = {}) {
            try {
                const queryString = new URLSearchParams(params).toString();
                return await apiRequest('GET', `/apartment-management/api/finance/financial_report.php?${queryString}`);
            } catch (error) {
                console.error('FinanceAPI.getReports error:', error);
                return { success: false, message: error.message, data: [] };
            }
        },
        
        async createIncome(incomeData) {
            try {
                return await apiRequest('POST', '/apartment-management/api/finance/gelirler.php', incomeData);
            } catch (error) {
                console.error('FinanceAPI.createIncome error:', error);
                return { success: false, message: error.message };
            }
        },
        
        async createExpense(expenseData) {
            try {
                return await apiRequest('POST', '/apartment-management/api/finance/giderler.php', expenseData);
            } catch (error) {
                console.error('FinanceAPI.createExpense error:', error);
                return { success: false, message: error.message };
            }
        }
    };
} else {
    if (!FinanceAPI.getReports) {
        FinanceAPI.getReports = async function(params = {}) {
            try {
                const queryString = new URLSearchParams(params).toString();
                return await apiRequest('GET', `/apartment-management/api/finance/financial_report.php?${queryString}`);
            } catch (error) {
                console.error('FinanceAPI.getReports error:', error);
                return { success: false, message: error.message, data: [] };
            }
        };
    }
}

if (typeof IncomeCategoryAPI === 'undefined') {
    window.IncomeCategoryAPI = {
        async getCategories() {
            try {
                return await apiRequest('GET', '/apartment-management/api/finance/income_categories.php');
            } catch (error) {
                console.error('IncomeCategoryAPI.getCategories error:', error);
                return { success: false, message: error.message, data: { categories: [] } };
            }
        }
    };
}

if (typeof PaymentRecordsAPI === 'undefined') {
    window.PaymentRecordsAPI = {
        async getPaymentRecords(params = {}) {
            try {
                const queryString = new URLSearchParams(params).toString();
                return await apiRequest('GET', `/apartment-management/api/dues/payment_records.php?${queryString}`);
            } catch (error) {
                console.error('PaymentRecordsAPI.getPaymentRecords error:', error);
                return { success: false, message: error.message };
            }
        },
        
        async getPaymentRecord(id) {
            try {
                return await apiRequest('GET', `/apartment-management/api/dues/payment_records.php?id=${id}`);
            } catch (error) {
                console.error('PaymentRecordsAPI.getPaymentRecord error:', error);
                return { success: false, message: error.message };
            }
        },
        
        async createPaymentRecord(data) {
            try {
                return await apiRequest('POST', '/apartment-management/api/dues/payment_records.php', data);
            } catch (error) {
                console.error('PaymentRecordsAPI.createPaymentRecord error:', error);
                return { success: false, message: error.message };
            }
        },
        
        async updatePaymentRecord(data) {
            try {
                return await apiRequest('PUT', '/apartment-management/api/dues/payment_records.php', data);
            } catch (error) {
                console.error('PaymentRecordsAPI.updatePaymentRecord error:', error);
                return { success: false, message: error.message };
            }
        },
        
        async deletePaymentRecord(id) {
            try {
                return await apiRequest('DELETE', `/apartment-management/api/dues/payment_records.php?id=${id}`);
            } catch (error) {
                console.error('PaymentRecordsAPI.deletePaymentRecord error:', error);
                return { success: false, message: error.message };
            }
        }
    };
}

window.DuesAPI = DuesAPI;
window.FinanceAPI = FinanceAPI;
window.IncomeCategoryAPI = IncomeCategoryAPI;
window.PaymentRecordsAPI = PaymentRecordsAPI;

console.log('Tüm eksik API\'ler yüklendi:', {
    DuesAPI: typeof DuesAPI !== 'undefined',
    FinanceAPI: typeof FinanceAPI !== 'undefined', 
    IncomeCategoryAPI: typeof IncomeCategoryAPI !== 'undefined',
    PaymentRecordsAPI: typeof PaymentRecordsAPI !== 'undefined',
    ExpenseAPI: typeof ExpenseAPI !== 'undefined'
});

window.ExpenseAPI = ExpenseAPI;

console.log('ExpenseAPI loaded successfully');
