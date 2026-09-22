
(function() {
    'use strict';
    
    console.log('🔄 gjderler.js başlatılıyor...');
    
    if (typeof ExpenseAPI === 'undefined') {
        console.error('❌ ExpenseAPI bulunamadı! api.js dosyası yüklenmemiş olabilir.');
        
        function showAPIError() {
            const errorHtml = `
                <div id="apiError" style="position: fixed; top: 20px; right: 20px; z-index: 9999; background: #dc3545; color: white; padding: 20px; border-radius: 8px; max-width: 400px; box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                        <strong>⚠️ API Yükleme Hatası</strong>
                        <button onclick="document.getElementById('apiError').remove()" style="background: none; border: none; color: white; font-size: 18px; cursor: pointer;">&times;</button>
                    </div>
                    <p style="margin: 0; font-size: 14px;">ExpenseAPI yüklenemedi. Sayfayı yenileyin veya geliştiriciyle iletişime geçin.</p>
                    <button onclick="location.reload()" style="background: rgba(255,255,255,0.2); border: 1px solid white; color: white; padding: 8px 16px; border-radius: 4px; cursor: pointer; margin-top: 10px;">Sayfayı Yenile</button>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', errorHtml);
        }
        
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', showAPIError);
        } else {
            showAPIError();
        }
        
        return;
    }
    
    console.log('✅ ExpenseAPI bulundu, gjderler.js devam ediyor...');
})();

if (typeof ExpenseAPI === 'undefined') {
    console.warn('⚠️ gjderler.js durduruldu - ExpenseAPI bulunamadı');
    return;
}

function waitForExpenseAPI(callback) {
    if (typeof ExpenseAPI !== 'undefined' && ExpenseAPI.getPaymentDetails) {
        console.log('✅ ExpenseAPI hazır');
        callback();
    } else {
        console.log('⏳ ExpenseAPI bekleniyor...');
        setTimeout(() => waitForExpenseAPI(callback), 200);
    }
}

function addFirmaClickListeners() {
    const firmaLinks = document.querySelectorAll('[data-firma]');
    firmaLinks.forEach(link => {
        link.style.cursor = 'pointer';
        link.style.color = '#007bff';
        link.style.textDecoration = 'underline';
        
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const firma = this.getAttribute('data-firma');
            openPaymentDetailsModal(firma);
        });
    });
}

function openPaymentDetailsModal(firma) {
    showLoadingModal();
    
    ExpenseAPI.getPaymentDetails(firma)
        .then(data => {
            hideLoadingModal();
            if (data.success) {
                showPaymentDetailsModal(data.data);
            } else {
                showErrorMessage('Veri getirilemedi: ' + data.message);
            }
        })
        .catch(error => {
            hideLoadingModal();
            showErrorMessage('Bir hata oluştu: ' + error.message);
        });
}

function showPaymentDetailsModal(data) {
    const modalHtml = `
        <div id="paymentDetailsModal" class="modal" style="display: block;">
            <div class="modal-content" style="max-width: 90%; width: 1200px;">
                <div class="modal-header">
                    <h2>
                        <i class="fas fa-building" style="margin-right: 8px;"></i>
                        ${data.firma} - Ödeme Detayları
                    </h2>
                    <span class="close" onclick="closePaymentDetailsModal()">&times;</span>
                </div>
                <div class="modal-body">
                    <!-- Özet Bilgiler -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px;">
                        <div style="background: #007bff; color: white; padding: 20px; border-radius: 8px; text-align: center;">
                            <h3 style="margin: 0 0 10px 0; font-size: 24px;">${data.summary.total_count}</h3>
                            <p style="margin: 0;">Toplam İşlem</p>
                        </div>
                        <div style="background: #28a745; color: white; padding: 20px; border-radius: 8px; text-align: center;">
                            <h3 style="margin: 0 0 10px 0; font-size: 24px;">${data.summary.paid_count}</h3>
                            <p style="margin: 0;">Ödenen</p>
                        </div>
                        <div style="background: #ffc107; color: white; padding: 20px; border-radius: 8px; text-align: center;">
                            <h3 style="margin: 0 0 10px 0; font-size: 24px;">${data.summary.unpaid_count}</h3>
                            <p style="margin: 0;">Ödenmemiş</p>
                        </div>
                        <div style="background: #17a2b8; color: white; padding: 20px; border-radius: 8px; text-align: center;">
                            <h3 style="margin: 0 0 10px 0; font-size: 24px;">${formatCurrency(data.summary.total_amount)}</h3>
                            <p style="margin: 0;">Toplam Tutar</p>
                        </div>
                    </div>
                    
                    <!-- Detay Tablosu -->
                    <div style="overflow-x: auto;">
                        <table class="table" style="width: 100%; border-collapse: collapse; background: white;">
                            <thead style="background: #343a40; color: white;">
                                <tr>
                                    <th style="padding: 12px; border: 1px solid #ddd;">Tarih</th>
                                    <th style="padding: 12px; border: 1px solid #ddd;">Açıklama</th>
                                    <th style="padding: 12px; border: 1px solid #ddd;">Kategori</th>
                                    <th style="padding: 12px; border: 1px solid #ddd;">Tutar</th>
                                    <th style="padding: 12px; border: 1px solid #ddd;">Ödeme Durumu</th>
                                    <th style="padding: 12px; border: 1px solid #ddd;">İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${generateExpenseRows(data.expenses)}
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" style="background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin-right: 10px;" onclick="closePaymentDetailsModal()">Kapat</button>
                    <button type="button" style="background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;" onclick="exportToExcel('${data.firma}')">
                        <i class="fas fa-file-excel" style="margin-right: 5px;"></i>Excel'e Aktar
                    </button>
                </div>
            </div>
        </div>
    `;
    
    const existingModal = document.getElementById('paymentDetailsModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
}

function closePaymentDetailsModal() {
    const modal = document.getElementById('paymentDetailsModal');
    if (modal) {
        modal.remove();
    }
}

function generateExpenseRows(expenses) {
    if (expenses.length === 0) {
        return '<tr><td colspan="6" style="text-align: center; padding: 20px;">Bu firmaya ait ödeme kaydı bulunamadı.</td></tr>';
    }
    
    return expenses.map(expense => `
        <tr style="border-bottom: 1px solid #ddd;">
            <td style="padding: 12px; border: 1px solid #ddd;">${expense.formatted_tarih}</td>
            <td style="padding: 12px; border: 1px solid #ddd;">${expense.aciklama || '-'}</td>
            <td style="padding: 12px; border: 1px solid #ddd;">
                <span style="background: #6c757d; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px;">${expense.kategori_adi || expense.kategori}</span>
            </td>
            <td style="padding: 12px; border: 1px solid #ddd; font-weight: bold;">${formatCurrency(expense.tutar)}</td>
            <td style="padding: 12px; border: 1px solid #ddd;">
                <span style="background: ${getPaymentStatusColor(expense.odeme_durumu)}; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px;">
                    ${getPaymentStatusText(expense.odeme_durumu)}
                </span>
            </td>
            <td style="padding: 12px; border: 1px solid #ddd;">
                <button style="background: #007bff; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; font-size: 12px;" onclick="viewExpenseDetail(${expense.id})">
                    <i class="fas fa-eye"></i> Görüntüle
                </button>
                ${expense.belge_url ? `
                    <a href="${expense.belge_url}" target="_blank" style="background: #17a2b8; color: white; text-decoration: none; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; font-size: 12px; margin-left: 5px; display: inline-block;">
                        <i class="fas fa-file-pdf"></i> PDF
                    </a>
                ` : ''}
            </td>
        </tr>
    `).join('');
}

function getPaymentStatusColor(status) {
    switch(status?.toLowerCase()) {
        case 'ödendi':
        case 'odendi':
            return '#28a745';
        case 'beklemede':
            return '#ffc107';
        case 'ödenmedi':
        case 'odenmedi':
            return '#dc3545';
        default:
            return '#6c757d';
    }
}

function getPaymentStatusText(status) {
    switch(status?.toLowerCase()) {
        case 'ödendi':
        case 'odendi':
            return 'Ödendi';
        case 'beklemede':
            return 'Beklemede';
        case 'ödenmedi':
        case 'odenmedi':
            return 'Ödenmedi';
        default:
            return status || 'Bilinmiyor';
    }
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('tr-TR', {
        style: 'currency',
        currency: 'TRY',
        minimumFractionDigits: 2
    }).format(parseFloat(amount) || 0);
}

function showLoadingModal() {
    const loadingHtml = `
        <div id="loadingModal" class="modal" style="display: block;">
            <div class="modal-content" style="max-width: 400px; text-align: center;">
                <div class="modal-body" style="padding: 40px;">
                    <div style="border: 4px solid #f3f3f3; border-top: 4px solid #3498db; border-radius: 50%; width: 50px; height: 50px; animation: spin 1s linear infinite; margin: 0 auto 20px;"></div>
                    <p style="margin: 0;">Veriler yükleniyor...</p>
                </div>
            </div>
        </div>
        <style>
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        </style>
    `;
    
    document.body.insertAdjacentHTML('beforeend', loadingHtml);
}

function hideLoadingModal() {
    const loadingModal = document.getElementById('loadingModal');
    if (loadingModal) {
        loadingModal.remove();
    }
}

function showErrorMessage(message) {
    const alertHtml = `
        <div id="errorAlert" style="position: fixed; top: 20px; right: 20px; z-index: 9999; background: #dc3545; color: white; padding: 15px 20px; border-radius: 5px; min-width: 300px; box-shadow: 0 4px 8px rgba(0,0,0,0.2);">
            <button onclick="document.getElementById('errorAlert').remove()" style="background: none; border: none; color: white; float: right; font-size: 18px; cursor: pointer; margin-left: 10px;">&times;</button>
            <strong>Hata:</strong> ${message}
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', alertHtml);
    
    setTimeout(() => {
        const alert = document.getElementById('errorAlert');
        if (alert) {
            alert.remove();
        }
    }, 5000);
}

function viewExpenseDetail(expenseId) {
    console.log('Expense detayı görüntüle:', expenseId);
}

function exportToExcel(firma) {
    if (typeof ExpenseAPI !== 'undefined' && ExpenseAPI.exportPayments) {
        ExpenseAPI.exportPayments(firma);
    } else {
        window.open(`export_payments.php?firma=${encodeURIComponent(firma)}`, '_blank');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    updateExistingFirmaLinks();
});

function updateExistingFirmaLinks() {
    setTimeout(() => {
        addFirmaClickListeners();
    }, 1000);
}

document.addEventListener('click', function(event) {
    const modal = document.getElementById('paymentDetailsModal');
    if (modal && event.target === modal) {
        closePaymentDetailsModal();
    }
});

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closePaymentDetailsModal();
    }
});

function waitForExpenseAPI(callback) {
    if (typeof ExpenseAPI !== 'undefined') {
        callback();
    } else {
        console.log('ExpenseAPI bekleniyor...');
        setTimeout(() => waitForExpenseAPI(callback), 100);
    }
}

function addFirmaClickListeners() {
    const firmaLinks = document.querySelectorAll('[data-firma]');
    firmaLinks.forEach(link => {
        link.style.cursor = 'pointer';
        link.style.color = '#007bff';
        link.style.textDecoration = 'underline';
        
        link.removeEventListener('click', handleFirmaClick);
        link.addEventListener('click', handleFirmaClick);
    });
}

function handleFirmaClick(e) {
    e.preventDefault();
    const firma = this.getAttribute('data-firma');
    
    if (typeof ExpenseAPI === 'undefined') {
        showErrorMessage('ExpenseAPI yüklenmedi. Lütfen sayfayı yenileyin.');
        return;
    }
    
    openPaymentDetailsModal(firma);
}

function openPaymentDetailsModal(firma) {
    console.log('Modal açılıyor, firma:', firma);
    
    showLoadingModal();
    
    ExpenseAPI.getPaymentDetails(firma)
        .then(data => {
            console.log('API yanıtı:', data);
            hideLoadingModal();
            if (data.success) {
                showPaymentDetailsModal(data.data);
            } else {
                showErrorMessage('Veri getirilemedi: ' + (data.message || 'Bilinmeyen hata'));
            }
        })
        .catch(error => {
            console.error('Modal açma hatası:', error);
            hideLoadingModal();
            showErrorMessage('Bir hata oluştu: ' + error.message);
        });
}

function showPaymentDetailsModal(data) {
    const modalHtml = `
        <div id="paymentDetailsModal" class="modal" style="display: block; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
            <div class="modal-content" style="background-color: white; margin: 2% auto; padding: 0; border-radius: 10px; width: 90%; max-width: 1200px; max-height: 90vh; overflow-y: auto;">
                <div class="modal-header" style="background: linear-gradient(135deg, #011500, #260399); color: white; padding: 20px; display: flex; justify-content: space-between; align-items: center; border-radius: 10px 10px 0 0;">
                    <h2 style="margin: 0; font-size: 1.5rem;">
                        <i class="fas fa-building" style="margin-right: 8px;"></i>
                        ${data.firma} - Ödeme Detayları
                    </h2>
                    <span class="close" onclick="closePaymentDetailsModal()" style="color: white; font-size: 28px; font-weight: bold; cursor: pointer; padding: 0.25rem;">&times;</span>
                </div>
                <div class="modal-body" style="padding: 30px;">
                    <!-- Özet Bilgiler -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
                        <div style="background: #007bff; color: white; padding: 25px; border-radius: 10px; text-align: center; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                            <h3 style="margin: 0 0 10px 0; font-size: 32px; font-weight: bold;">${data.summary.total_count}</h3>
                            <p style="margin: 0; font-size: 14px;">Toplam İşlem</p>
                        </div>
                        <div style="background: #28a745; color: white; padding: 25px; border-radius: 10px; text-align: center; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                            <h3 style="margin: 0 0 10px 0; font-size: 32px; font-weight: bold;">${data.summary.paid_count}</h3>
                            <p style="margin: 0; font-size: 14px;">Ödenen</p>
                        </div>
                        <div style="background: #ffc107; color: white; padding: 25px; border-radius: 10px; text-align: center; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                            <h3 style="margin: 0 0 10px 0; font-size: 32px; font-weight: bold;">${data.summary.unpaid_count}</h3>
                            <p style="margin: 0; font-size: 14px;">Ödenmemiş</p>
                        </div>
                        <div style="background: #17a2b8; color: white; padding: 25px; border-radius: 10px; text-align: center; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                            <h3 style="margin: 0 0 10px 0; font-size: 32px; font-weight: bold;">${formatCurrency(data.summary.total_amount)}</h3>
                            <p style="margin: 0; font-size: 14px;">Toplam Tutar</p>
                        </div>
                    </div>
                    
                    <!-- Detay Tablosu -->
                    <div style="overflow-x: auto; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        <table style="width: 100%; border-collapse: collapse; background: white;">
                            <thead>
                                <tr style="background: #343a40; color: white;">
                                    <th style="padding: 15px 12px; border: none; text-align: left; font-weight: 600;">Tarih</th>
                                    <th style="padding: 15px 12px; border: none; text-align: left; font-weight: 600;">Açıklama</th>
                                    <th style="padding: 15px 12px; border: none; text-align: left; font-weight: 600;">Kategori</th>
                                    <th style="padding: 15px 12px; border: none; text-align: left; font-weight: 600;">Tutar</th>
                                    <th style="padding: 15px 12px; border: none; text-align: left; font-weight: 600;">Ödeme Durumu</th>
                                    <th style="padding: 15px 12px; border: none; text-align: left; font-weight: 600;">İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${generateExpenseRows(data.expenses)}
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer" style="padding: 20px 30px; border-top: 1px solid #eee; display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" style="background: #6c757d; color: white; border: none; padding: 12px 24px; border-radius: 5px; cursor: pointer; font-size: 14px;" onclick="closePaymentDetailsModal()">Kapat</button>
                    <button type="button" style="background: #28a745; color: white; border: none; padding: 12px 24px; border-radius: 5px; cursor: pointer; font-size: 14px;" onclick="exportToExcel('${data.firma}')">
                        <i class="fas fa-file-excel" style="margin-right: 8px;"></i>Excel'e Aktar
                    </button>
                </div>
            </div>
        </div>
    `;
    
    const existingModal = document.getElementById('paymentDetailsModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
}

function closePaymentDetailsModal() {
    const modal = document.getElementById('paymentDetailsModal');
    if (modal) {
        modal.remove();
    }
}

function generateExpenseRows(expenses) {
    if (!expenses || expenses.length === 0) {
        return '<tr><td colspan="6" style="text-align: center; padding: 30px; color: #666; font-style: italic;">Bu firmaya ait ödeme kaydı bulunamadı.</td></tr>';
    }
    
    return expenses.map(expense => `
        <tr style="border-bottom: 1px solid #f0f0f0; transition: background-color 0.2s;">
            <td style="padding: 15px 12px;">${expense.formatted_tarih || expense.tarih || '-'}</td>
            <td style="padding: 15px 12px;">${expense.aciklama || expense.description || '-'}</td>
            <td style="padding: 15px 12px;">
                <span style="background: #6c757d; color: white; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 500;">${expense.kategori_adi || expense.kategori || expense.category || '-'}</span>
            </td>
            <td style="padding: 15px 12px; font-weight: bold; color: #28a745;">${formatCurrency(expense.tutar || expense.amount)}</td>
            <td style="padding: 15px 12px;">
                <span style="background: ${getPaymentStatusColor(expense.odeme_durumu || expense.payment_status)}; color: white; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 500;">
                    ${getPaymentStatusText(expense.odeme_durumu || expense.payment_status)}
                </span>
            </td>
            <td style="padding: 15px 12px;">
                <button style="background: #007bff; color: white; border: none; padding: 8px 16px; border-radius: 5px; cursor: pointer; font-size: 12px; margin-right: 5px;" onclick="viewExpenseDetail(${expense.id})" title="Detayları Görüntüle">
                    <i class="fas fa-eye"></i>
                </button>
                ${expense.belge_url ? `
                    <a href="${expense.belge_url}" target="_blank" style="background: #17a2b8; color: white; text-decoration: none; padding: 8px 16px; border-radius: 5px; font-size: 12px; display: inline-block;" title="Belgeyi Görüntüle">
                        <i class="fas fa-file-pdf"></i>
                    </a>
                ` : ''}
            </td>
        </tr>
    `).join('');
}

function getPaymentStatusColor(status) {
    switch(status?.toLowerCase()) {
        case 'ödendi':
        case 'odendi':
        case 'paid':
            return '#28a745';
        case 'beklemede':
        case 'pending':
            return '#ffc107';
        case 'ödenmedi':
        case 'odenmedi':
        case 'unpaid':
            return '#dc3545';
        default:
            return '#6c757d';
    }
}

function getPaymentStatusText(status) {
    switch(status?.toLowerCase()) {
        case 'ödendi':
        case 'odendi':
        case 'paid':
            return 'Ödendi';
        case 'beklemede':
        case 'pending':
            return 'Beklemede';
        case 'ödenmedi':
        case 'odenmedi':
        case 'unpaid':
            return 'Ödenmedi';
        default:
            return status || 'Bilinmiyor';
    }
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('tr-TR', {
        style: 'currency',
        currency: 'TRY',
        minimumFractionDigits: 2
    }).format(parseFloat(amount) || 0);
}

function showLoadingModal() {
    const loadingHtml = `
        <div id="loadingModal" class="modal" style="display: block; position: fixed; z-index: 1001; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 40px; border-radius: 10px; text-align: center; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
                <div style="border: 4px solid #f3f3f3; border-top: 4px solid #3498db; border-radius: 50%; width: 50px; height: 50px; animation: spin 1s linear infinite; margin: 0 auto 20px;"></div>
                <p style="margin: 0; color: #333; font-size: 16px;">Veriler yükleniyor...</p>
            </div>
        </div>
        <style>
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        </style>
    `;
    
    document.body.insertAdjacentHTML('beforeend', loadingHtml);
}

function hideLoadingModal() {
    const loadingModal = document.getElementById('loadingModal');
    if (loadingModal) {
        loadingModal.remove();
    }
}

function showErrorMessage(message) {
    const alertHtml = `
        <div id="errorAlert" style="position: fixed; top: 20px; right: 20px; z-index: 9999; background: #dc3545; color: white; padding: 15px 20px; border-radius: 8px; min-width: 300px; box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3); font-family: system-ui, -apple-system, sans-serif;">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <strong style="display: block; margin-bottom: 5px;">Hata</strong>
                    <span style="font-size: 14px;">${message}</span>
                </div>
                <button onclick="document.getElementById('errorAlert').remove()" style="background: none; border: none; color: white; font-size: 20px; cursor: pointer; margin-left: 15px; padding: 0; line-height: 1;">&times;</button>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', alertHtml);
    
    setTimeout(() => {
        const alert = document.getElementById('errorAlert');
        if (alert) {
            alert.remove();
        }
    }, 5000);
}

function viewExpenseDetail(expenseId) {
    console.log('Expense detayı görüntüle:', expenseId);
    showErrorMessage('Expense detay görüntüleme özelliği henüz eklenmedi.');
}

function exportToExcel(firma) {
    if (typeof ExpenseAPI !== 'undefined' && ExpenseAPI.exportPayments) {
        ExpenseAPI.exportPayments(firma);
    } else {
        window.open(`export_payments.php?firma=${encodeURIComponent(firma)}`, '_blank');
    }
}

document.addEventListener('click', function(event) {
    const modal = document.getElementById('paymentDetailsModal');
    if (modal && event.target === modal) {
        closePaymentDetailsModal();
    }
});

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closePaymentDetailsModal();
    }
});

document.addEventListener('DOMContentLoaded', function() {
    console.log('gjderler.js yüklendi');
    waitForExpenseAPI(function() {
        console.log('ExpenseAPI hazır, firma linkleri ekleniyor');
        // Kısa bir süre bekle ki DOM güncellensin
        setTimeout(() => {
            addFirmaClickListeners();
        }, 1000);
    });
});

window.addFirmaClickListeners = addFirmaClickListeners;