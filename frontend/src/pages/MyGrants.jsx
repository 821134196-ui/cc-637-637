import { useEffect, useState } from 'react'
import { api } from '../api'

const STATUS_TEXT = { active: '生效中', expired: '已到期', revoked: '已撤回' }

export default function MyGrants() {
  const [grants, setGrants] = useState([])
  const [links, setLinks] = useState({})
  const [error, setError] = useState('')

  function refresh() {
    api.get('/api/grants').then(setGrants).catch((e) => setError(e.message))
  }

  useEffect(refresh, [])

  async function createLink(grantId) {
    setError('')
    try {
      const data = await api.post(`/api/grants/${grantId}/download-links`)
      setLinks((cur) => ({ ...cur, [grantId]: data.url }))
    } catch (e) {
      setError(e.message)
    }
  }

  return (
    <>
      <h1>我的下载（已获批范围）</h1>
      <p className="muted">此处仅列出已获批的授权。下载内容严格限定在授权版本与授权字段内；授权到期或被撤回后，下载链接立即失效。</p>
      {error && <p className="error">{error}</p>}
      {grants.length === 0 && !error && <div className="card"><p className="muted">暂无获批授权。</p></div>}
      {grants.map((g) => (
        <div className="card" key={g.id}>
          <h3>
            {g.dataset.name} v{g.version.version}{' '}
            <span className={`badge ${g.status}`}>{STATUS_TEXT[g.status]}</span>
          </h3>
          <p>
            获批字段：{g.granted_fields.map((f) => <span key={f} className="badge public" style={{ marginRight: 6 }}>{f}</span>)}
          </p>
          <p className="muted">使用期限：{g.period_start} 至 {g.period_end}</p>
          {g.status === 'revoked' && <p className="error">撤回原因:{g.revoke_reason}</p>}
          {g.status === 'active' && (
            <div className="actions">
              <button className="primary" onClick={() => createLink(g.id)}>生成下载链接</button>
            </div>
          )}
          {links[g.id] && (
            <div className="linkbox">
              <code>{links[g.id]}</code>
              <a href={links[g.id]} download><button>下载 CSV</button></a>
            </div>
          )}
        </div>
      ))}
    </>
  )
}
