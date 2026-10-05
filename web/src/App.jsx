import React, { useEffect, useState } from 'react';
import { Link, NavLink, Route, Routes, useNavigate } from 'react-router-dom';
import { api, clearSession, getUser } from './api';
import Catalog from './pages/Catalog';
import DatasetDetail from './pages/DatasetDetail';
import Login from './pages/Login';
import Apply from './pages/Apply';
import MyApplications from './pages/MyApplications';
import MyGrants from './pages/MyGrants';
import Citations from './pages/Citations';
import Admin from './pages/Admin';

export default function App() {
  const [user, setUser] = useState(getUser());
  const navigate = useNavigate();

  useEffect(() => {
    const onLogin = () => setUser(getUser());
    window.addEventListener('session-changed', onLogin);
    return () => window.removeEventListener('session-changed', onLogin);
  }, []);

  const logout = async () => {
    try { await api('/logout', { method: 'POST' }); } catch { /* token may be gone */ }
    clearSession();
    setUser(null);
    navigate('/login');
  };

  return (
    <div>
      <header className="topbar">
        <Link to="/" className="brand">科研数据集访问与引用追踪</Link>
        <nav>
          <NavLink to="/">公开目录</NavLink>
          <NavLink to="/citations">成果引用登记册</NavLink>
          {user && <NavLink to="/applications">我的申请</NavLink>}
          {user && <NavLink to="/grants">已获批范围与下载</NavLink>}
          {user?.role === 'approver' && <NavLink to="/admin">审批管理</NavLink>}
        </nav>
        <div className="session">
          {user ? (
            <>
              <span>{user.name}（{user.role === 'approver' ? '审批人' : '研究人员'}）</span>
              <button onClick={logout}>退出</button>
            </>
          ) : (
            <Link to="/login">登录</Link>
          )}
        </div>
      </header>
      <main className="container">
        <Routes>
          <Route path="/" element={<Catalog />} />
          <Route path="/datasets/:id" element={<DatasetDetail user={user} />} />
          <Route path="/login" element={<Login />} />
          <Route path="/apply/:datasetId" element={<Apply user={user} />} />
          <Route path="/applications" element={<MyApplications />} />
          <Route path="/grants" element={<MyGrants />} />
          <Route path="/citations" element={<Citations user={user} />} />
          <Route path="/admin" element={<Admin user={user} />} />
          <Route path="*" element={<Catalog />} />
        </Routes>
      </main>
    </div>
  );
}
