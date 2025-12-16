import { useTranslation } from 'react-i18next';
import { ProForm, ProFormText } from '@ant-design/pro-components';
import { Card, Descriptions, Row, Col, App, Tag } from 'antd';

import { useAuth } from '@/contexts/auth-context';
import { authApi } from '@/lib/auth-api';
import { validateChangePasswordForm } from '@/lib/validation';
import type { ApiError } from '@/types/auth';

export function ProfilePage() {
  const { t } = useTranslation();
  const { user } = useAuth();
  const { message } = App.useApp();

  const handleChangePassword = async (values: {
    currentPassword: string;
    newPassword: string;
    confirmPassword: string;
  }) => {
    const validation = validateChangePasswordForm(
      values.currentPassword,
      values.newPassword,
      values.confirmPassword
    );

    if (!validation.isValid) {
      const firstError = Object.values(validation.errors)[0];
      message.error(firstError);
      return false;
    }

    try {
      await authApi.changePassword({
        currentPassword: values.currentPassword,
        newPassword: values.newPassword,
      });
      message.success('Password changed successfully!');
      return true;
    } catch (error) {
      const apiErr = error as ApiError;
      message.error(apiErr.error || 'Failed to change password. Please try again.');
      return false;
    }
  };

  return (
    <div className="space-y-6">
      <h1 className="text-xl font-semibold">{t('header.profile')}</h1>

      <Row gutter={[24, 24]}>
        {/* User Info Card */}
        <Col xs={24} md={12}>
          <Card title="Account Information">
            <Descriptions column={1} bordered size="small">
              <Descriptions.Item label="User ID">
                <code className="text-sm">{user?.id}</code>
              </Descriptions.Item>
              <Descriptions.Item label="Email">
                {user?.email}
              </Descriptions.Item>
              <Descriptions.Item label="Email Verified">
                <Tag color={user?.isVerified ? 'success' : 'warning'}>
                  {user?.isVerified ? 'Yes' : 'No'}
                </Tag>
              </Descriptions.Item>
              <Descriptions.Item label="Account Created">
                {user?.createdAt && new Date(user.createdAt).toLocaleString()}
              </Descriptions.Item>
              <Descriptions.Item label="Roles">
                {user?.roles.map((role) => (
                  <Tag key={role} color="blue">{role}</Tag>
                ))}
              </Descriptions.Item>
            </Descriptions>
          </Card>
        </Col>

        {/* Change Password Card */}
        <Col xs={24} md={12}>
          <Card title="Change Password">
            <ProForm
              layout="vertical"
              onFinish={handleChangePassword}
              submitter={{
                searchConfig: {
                  submitText: 'Change Password',
                },
                resetButtonProps: { style: { display: 'none' } },
              }}
            >
              <ProFormText.Password
                name="currentPassword"
                label="Current Password"
                rules={[{ required: true, message: 'Please enter current password' }]}
              />
              <ProFormText.Password
                name="newPassword"
                label="New Password"
                rules={[{ required: true, message: 'Please enter new password' }]}
                extra="Min 8 characters with uppercase, lowercase, and number"
              />
              <ProFormText.Password
                name="confirmPassword"
                label="Confirm New Password"
                rules={[
                  { required: true, message: 'Please confirm new password' },
                  ({ getFieldValue }) => ({
                    validator(_, value) {
                      if (!value || getFieldValue('newPassword') === value) {
                        return Promise.resolve();
                      }
                      return Promise.reject(new Error('Passwords do not match'));
                    },
                  }),
                ]}
              />
            </ProForm>
          </Card>
        </Col>
      </Row>
    </div>
  );
}
