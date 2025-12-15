// Password validation regex matching backend: /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/
export const PASSWORD_REGEX = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/;
export const MIN_PASSWORD_LENGTH = 8;

export const EMAIL_REGEX = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

export interface ValidationResult {
  isValid: boolean;
  errors: Record<string, string>;
}

export function validateEmail(email: string): string | null {
  if (!email) {
    return 'Email is required';
  }
  if (!EMAIL_REGEX.test(email)) {
    return 'Invalid email format';
  }
  return null;
}

export function validatePassword(password: string): string | null {
  if (!password) {
    return 'Password is required';
  }
  if (password.length < MIN_PASSWORD_LENGTH) {
    return `Password must be at least ${MIN_PASSWORD_LENGTH} characters`;
  }
  if (!PASSWORD_REGEX.test(password)) {
    return 'Password must contain at least one uppercase letter, one lowercase letter, and one number';
  }
  return null;
}

export function validateLoginForm(email: string, password: string): ValidationResult {
  const errors: Record<string, string> = {};

  const emailError = validateEmail(email);
  if (emailError) errors.email = emailError;

  if (!password) errors.password = 'Password is required';

  return {
    isValid: Object.keys(errors).length === 0,
    errors,
  };
}

export function validateRegisterForm(
  email: string,
  password: string,
  confirmPassword: string
): ValidationResult {
  const errors: Record<string, string> = {};

  const emailError = validateEmail(email);
  if (emailError) errors.email = emailError;

  const passwordError = validatePassword(password);
  if (passwordError) errors.password = passwordError;

  if (password !== confirmPassword) {
    errors.confirmPassword = 'Passwords do not match';
  }

  return {
    isValid: Object.keys(errors).length === 0,
    errors,
  };
}

export function validateResetPasswordForm(
  password: string,
  confirmPassword: string
): ValidationResult {
  const errors: Record<string, string> = {};

  const passwordError = validatePassword(password);
  if (passwordError) errors.password = passwordError;

  if (password !== confirmPassword) {
    errors.confirmPassword = 'Passwords do not match';
  }

  return {
    isValid: Object.keys(errors).length === 0,
    errors,
  };
}

export function validateChangePasswordForm(
  currentPassword: string,
  newPassword: string,
  confirmPassword: string
): ValidationResult {
  const errors: Record<string, string> = {};

  if (!currentPassword) errors.currentPassword = 'Current password is required';

  const passwordError = validatePassword(newPassword);
  if (passwordError) errors.newPassword = passwordError;

  if (newPassword !== confirmPassword) {
    errors.confirmPassword = 'Passwords do not match';
  }

  return {
    isValid: Object.keys(errors).length === 0,
    errors,
  };
}
