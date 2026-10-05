import { Link, Navigate, NavLink, Route, Routes, useNavigate } from 'react-router-dom'
import { useAuth } from './auth'
import Catalog from './pages/Catalog'
import DatasetDetail from './pages/DatasetDetail'
import Login from './pages/Login'
import Apply from './pages/Apply'
import MyRequests from './pages/MyRequests'
import MyGrants from './pages/MyGrants'
import MyCitations from './pages/MyCitations'
import AdminRequests from './pages/admin/AdminRequests'
import AdminGrants from './pages/admin/AdminGrants'
import AdminDatasets from './pages/admin/AdminDatasets'
import AdminCitations from './pages/admin/AdminCitations'

function RequireAuth({ children }) {
  const { user, loading } = useAuth()
  if (loading) return <div className="page"><p>加载中…</p></div>
  if (!user) return <Navigate to="/login" replace />
  return children
}

function RequireAdmin({ children }) {
  const { user, loading } = useAuth()
  if (loading) return <div className="page"><p>加载中…</p></div>
  if (!user) return <Navigate to="/login" replace />
  if (user.role !== 'admin') return <div className="page"><p className="error">需要管理员权限</p></div>
  return children
}

function Layout() {
  const { user, logout } = useAuth()
  const navigate = useNavigate()

  return (
    <>
      <header className="topbar">
        <Link to="/" className="brand">科研数据平台</Link>
        <nav>
          <NavLink to="/" end>公开目录</NavLink>
          {user && <NavLink to="/requests">我的申请</NavLink>}
          {user && <NavLink to="/downloads">我的下载</NavLink>}
          {user && <NavLink to="/citations">成果引用</NavLink>}
          {user?.role === 'admin' && (
            <>
              <span className="nav-sep">管理</span>
              <NavLink to="/admin/requests">审批</NavLink>
              <NavLink to="/admin/grants">授权</NavLink>
              <NavLink to="/admin/datasets">数据集</NavLink>
              <NavLink to="/admin/citations">引用记录</NavLink>
            </>
          )}
        </nav>
        <div className="account">
          {user ? (
            <>
              <span className="who">{user.name}（{user.role === 'admin' ? '管理员' : '研究员'}）</span>
              <button className="link" onClick={async () => { await logout(); navigate('/') }}>退出</button>
            </>
          ) : (
            <Link to="/login">登录</Link>
          )}
        </div>
      </header>
      <main className="page">
        <Routes>
          <Route path="/" element={<Catalog />} />
          <Route path="/datasets/:id" element={<DatasetDetail />} />
          <Route path="/login" element={<Login />} />
          <Route path="/apply/:datasetId" element={<RequireAuth><Apply /></RequireAuth>} />
          <Route path="/requests" element={<RequireAuth><MyRequests /></RequireAuth>} />
          <Route path="/downloads" element={<RequireAuth><MyGrants /></RequireAuth>} />
          <Route path="/citations" element={<RequireAuth><MyCitations /></RequireAuth>} />
          <Route path="/admin/requests" element={<RequireAdmin><AdminRequests /></RequireAdmin>} />
          <Route path="/admin/grants" element={<RequireAdmin><AdminGrants /></RequireAdmin>} />
          <Route path="/admin/datasets" element={<RequireAdmin><AdminDatasets /></RequireAdmin>} />
          <Route path="/admin/citations" element={<RequireAdmin><AdminCitations /></RequireAdmin>} />
        </Routes>
      </main>
    </>
  )
}

export default function App() {
  return <Layout />
}
