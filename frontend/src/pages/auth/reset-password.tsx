import { Link, useSearchParams, useNavigate } from 'react-router';
import { useTranslation } from 'react-i18next';
import { ProForm, ProFormText } from '@ant-design/pro-components';
import { Card, Result, App } from 'antd';
import { LockOutlined } from '@ant-design/icons';

import { authApi } from '@/lib/auth-api';
import { validateResetPasswordForm } from '@/lib/validation';
import type { ApiError } from '@/types/auth';

export function ResetPasswordPage() {
  const { t } = useTranslation();
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const { message } = App.useApp();
  const token = searchParams.get('token') || '';

  if (!token) {
    return (
      <Card>
        <Result
          status="error"
          title={t('auth.invalidResetLink')}
          subTitle={t('auth.resetLinkExpired')}
          extra={
            <Link to="/forgot-password" className="text-blue-500 hover:underline">
              {t('auth.requestNewLink')}
            </Link>
          }
        />
      </Card>
    );
  }

  const handleSubmit = async (values: {
    password: string;
    confirmPassword: string;
  }) => {
    const validation = validateResetPasswordForm(
      values.password,
      values.confirmPassword
    );
    if (!validation.isValid) {
      const firstError = Object.values(validation.errors)[0];
      message.error(firstError);
      return false;
    }

    try {
      await authApi.resetPassword({ token, password: values.password });
      navigate('/login', {
        state: {
          message: t('auth.resetSuccess'),
        },
      });
      return true;
    } catch (error) {
      const apiErr = error as ApiError;
      message.error(
        apiErr.error || t('auth.resetFailed')
      );
      return false;
    }
  };

  return (
    <Card>
      <div className="text-center mb-6">
        <h2 className="text-2xl font-semibold">{t('auth.resetPassword')}</h2>
        <p className="text-gray-500">{t('auth.resetPasswordSubtitle')}</p>
      </div>

      <ProForm
        layout="vertical"
        onFinish={handleSubmit}
        submitter={{
          searchConfig: {
            submitText: t('auth.resetPassword'),
          },
          resetButtonProps: { style: { display: 'none' } },
          submitButtonProps: { block: true, size: 'large' },
        }}
      >
        <ProFormText.Password
          name="password"
          label={t('auth.newPassword')}
          fieldProps={{
            size: 'large',
            prefix: <LockOutlined />,
          }}
          rules={[{ required: true, message: t('auth.enterNewPassword') }]}
          extra={t('auth.passwordRequirements')}
        />
        <ProFormText.Password
          name="confirmPassword"
          label={t('auth.confirmNewPassword')}
          fieldProps={{
            size: 'large',
            prefix: <LockOutlined />,
          }}
          rules={[
            { required: true, message: t('auth.confirmPasswordRequired') },
            ({ getFieldValue }) => ({
              validator(_, value) {
                if (!value || getFieldValue('password') === value) {
                  return Promise.resolve();
                }
                return Promise.reject(new Error(t('auth.passwordMismatch')));
              },
            }),
          ]}
        />
      </ProForm>
    </Card>
  );
}
