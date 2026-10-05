import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { api, saveSession } from '../api';

export default function Login() {
  const [email, setEmail] = useState('researcher@example.edu');
  const [password, setPassword] = useState('password');
  const [error, setError] = useState('');
  const navigate = useNavigate();

  const submit = async (e) => {
    e.preventDefault();
    setError('');
    try {
      const data = await api('/login', { method: 'POST', body: { email, password } });
      saveSession(data.token, data.user);
      window.dispatchEvent(new Event('session-changed'));
      navigate('/');
    } catch (err) {
      setError(err.message);
    }
  };

  return (
    <section className="narrow">
      <h1>登录</h1>
      <form onSubmit={submit}>
        <label>邮箱
          <input value={email} onChange={(e) => setEmail(e.target.value)} type="email" required />
        </label>
        <label>密码
          <input value={password} onChange={(e) => setPassword(e.target.value)} type="password" required />
        </label>
        {error && <p className="error">{error}</p>}
        <button type="submit">登录</button>
      </form>
      <p className="hint">
        演示账号：研究人员 researcher@example.edu / 审批人 approver@example.edu，密码均为 password
      </p>
    </section>
  );
}
