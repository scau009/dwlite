import { Link, useNavigate } from 'react-router';
import { useTranslation } from 'react-i18next';
import { ProForm, ProFormText } from '@ant-design/pro-components';
import { Card, App } from 'antd';
import { UserOutlined, LockOutlined } from '@ant-design/icons';

import { useAuth } from '@/contexts/auth-context';
import { validateRegisterForm } from '@/lib/validation';
import type { ApiError } from '@/types/auth';

export function RegisterPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { register } = useAuth();
  const { message } = App.useApp();

  const handleSubmit = async (values: {
    email: string;
    password: string;
    confirmPassword: string;
  }) => {
    const validation = validateRegisterForm(
      values.email,
      values.password,
      values.confirmPassword
    );
    if (!validation.isValid) {
      const firstError = Object.values(validation.errors)[0];
      message.error(firstError);
      return false;
    }

    try {
      const result = await register({
        email: values.email,
        password: values.password,
      });
      navigate('/login', { state: { message: result.message } });
      return true;
    } catch (error) {
      const apiErr = error as ApiError;
      message.error(apiErr.error || t('auth.registrationFailed'));
      return false;
    }
  };

  return (
    <Card>
      <div className="text-center mb-6">
        <h2 className="text-2xl font-semibold">{t('auth.register')}</h2>
        <p className="text-gray-500">{t('auth.registerSubtitle')}</p>
      </div>

      <ProForm
        layout="vertical"
        onFinish={handleSubmit}
        submitter={{
          searchConfig: {
            submitText: t('auth.createAccount'),
          },
          resetButtonProps: { style: { display: 'none' } },
          submitButtonProps: { block: true, size: 'large' },
        }}
      >
        <ProFormText
          name="email"
          label={t('auth.email')}
          fieldProps={{
            size: 'large',
            prefix: <UserOutlined />,
          }}
          placeholder={t('auth.emailPlaceholder')}
          rules={[
            { required: true, message: t('auth.emailRequired') },
            { type: 'email', message: t('auth.emailInvalid') },
          ]}
        />
        <ProFormText.Password
          name="password"
          label={t('auth.password')}
          fieldProps={{
            size: 'large',
            prefix: <LockOutlined />,
          }}
          placeholder={t('auth.password')}
          rules={[{ required: true, message: t('auth.passwordRequired') }]}
          extra={t('auth.passwordRequirements')}
        />
        <ProFormText.Password
          name="confirmPassword"
          label={t('auth.confirmPassword')}
          fieldProps={{
            size: 'large',
            prefix: <LockOutlined />,
          }}
          placeholder={t('auth.confirmPassword')}
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

      <div className="text-center mt-4">
        {t('auth.hasAccount')}{' '}
        <Link to="/login" className="text-blue-500 hover:underline">
          {t('auth.login')}
        </Link>
      </div>
    </Card>
  );
}
