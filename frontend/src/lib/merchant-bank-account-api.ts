import { apiFetch } from './api-client';

// Types
export interface BankAccount {
  id: string;
  bankName: string;
  bankCode: string | null;
  branchName: string | null;
  maskedAccountNumber: string;
  accountNumber?: string;
  accountHolder: string;
  accountType: 'corporate' | 'personal';
  currency: string;
  status: 'pending' | 'active' | 'disabled';
  isDefault: boolean;
  createdAt: string;
  updatedAt: string;
}

export interface CreateBankAccountRequest {
  bankName: string;
  bankCode?: string;
  branchName?: string;
  accountNumber: string;
  accountHolder: string;
  accountType: 'corporate' | 'personal';
  currency?: string;
}

export interface UpdateBankAccountRequest {
  bankName: string;
  bankCode?: string;
  branchName?: string;
  accountNumber: string;
  accountHolder: string;
  accountType: 'corporate' | 'personal';
  currency?: string;
}

// Status labels and colors
export const BANK_ACCOUNT_STATUS_LABELS: Record<string, string> = {
  pending: 'bankAccounts.statusPending',
  active: 'bankAccounts.statusActive',
  disabled: 'bankAccounts.statusDisabled',
};

export const BANK_ACCOUNT_STATUS_COLORS: Record<string, string> = {
  pending: 'blue',
  active: 'green',
  disabled: 'default',
};

export const BANK_ACCOUNT_TYPE_LABELS: Record<string, string> = {
  corporate: 'bankAccounts.typeCorporate',
  personal: 'bankAccounts.typePersonal',
};

// API functions
export const merchantBankAccountApi = {
  /**
   * Get bank account list
   */
  async getBankAccounts(): Promise<{ data: BankAccount[]; total: number }> {
    return apiFetch<{ data: BankAccount[]; total: number }>('/api/merchant/bank-accounts');
  },

  /**
   * Get bank account detail
   */
  async getBankAccount(id: string): Promise<BankAccount> {
    return apiFetch<BankAccount>(`/api/merchant/bank-accounts/${id}`);
  },

  /**
   * Create bank account
   */
  async createBankAccount(data: CreateBankAccountRequest): Promise<{ message: string; data: BankAccount }> {
    return apiFetch<{ message: string; data: BankAccount }>('/api/merchant/bank-accounts', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data),
    });
  },

  /**
   * Update bank account
   */
  async updateBankAccount(id: string, data: UpdateBankAccountRequest): Promise<{ message: string; data: BankAccount }> {
    return apiFetch<{ message: string; data: BankAccount }>(`/api/merchant/bank-accounts/${id}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data),
    });
  },

  /**
   * Delete bank account
   */
  async deleteBankAccount(id: string): Promise<{ message: string }> {
    return apiFetch<{ message: string }>(`/api/merchant/bank-accounts/${id}`, {
      method: 'DELETE',
    });
  },

  /**
   * Set bank account as default
   */
  async setDefaultBankAccount(id: string): Promise<{ message: string; data: BankAccount }> {
    return apiFetch<{ message: string; data: BankAccount }>(`/api/merchant/bank-accounts/${id}/default`, {
      method: 'PUT',
    });
  },
};
