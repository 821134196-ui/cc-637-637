import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuth } from '../auth'

export default function Login() {
  const { login } = useAuth()
  const navigate = useNavigate()
  const [email, setEmail] = useState('researcher@school.edu')
  const [password, setPassword] = useState('')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)

  async function submit(e) {
    e.preventDefault()
    setBusy(true)
    setError('')
    try {
      const user = await login(email, password)
      navigate(user.role === 'admin' ? '/admin/requests' : '/')
    } catch (err) {
      setError(err.message)
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="card" style={{ maxWidth: 420, margin: '40px auto' }}>
      <h1>登录</h1>
      <form onSubmit={submit}>
        <label>邮箱</label>
        <input value={email} onChange={(e) => setEmail(e.target.value)} type="email" required />
        <label>密码</label>
        <input value={password} onChange={(e) => setPassword(e.target.value)} type="password" required />
        {error && <p className="error">{error}</p>}
        <div className="actions">
          <button className="primary" disabled={busy}>{busy ? '登录中…' : '登录'}</button>
        </div>
      </form>
      <p className="muted" style={{ marginTop: 16 }}>
        演示账号：研究员 researcher@school.edu / research123；管理员 admin@school.edu / admin123
      </p>
    </div>
  )
}
