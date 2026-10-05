import { useEffect, useState } from 'react'
import { api } from '../../api'

const STATUS_TEXT = { active: '生效中', expired: '已到期', revoked: '已撤回' }

export default function AdminGrants() {
  const [grants, setGrants] = useState([])
  const [error, setError] = useState('')

  function refresh() {
    api.get('/api/admin/grants').then(setGrants).catch((e) => setError(e.message))
  }

  useEffect(refresh, [])

  async function revoke(g) {
    const reason = window.prompt(`请输入撤回授权 #${g.id} 的原因（撤回后下载链接立即失效，已登记引用保留）：`)
    if (!reason) return
    setError('')
    try {
      await api.post(`/api/admin/grants/${g.id}/revoke`, { reason })
      refresh()
    } catch (e) { setError(e.message) }
  }

  return (
    <>
      <h1>授权管理</h1>
      {error && <p className="error">{error}</p>}
      <div className="card">
        <table>
          <thead>
            <tr><th>ID</th><th>用户</th><th>数据集 / 版本</th><th>授权字段</th><th>期限</th><th>状态</th><th></th></tr>
          </thead>
          <tbody>
            {grants.map((g) => (
              <tr key={g.id}>
                <td>#{g.id}</td>
                <td>{g.user.name}</td>
                <td>{g.dataset.name} v{g.version.version}</td>
                <td>{g.granted_fields.join('、')}</td>
                <td className="muted">{g.period_start} ~ {g.period_end}</td>
                <td><span className={`badge ${g.status}`}>{STATUS_TEXT[g.status]}</span></td>
                <td>
                  {g.status === 'active' && <button className="danger" onClick={() => revoke(g)}>撤回</button>}
                  {g.status === 'revoked' && <span className="muted">{g.revoke_reason}</span>}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        {grants.length === 0 && <p className="muted">暂无授权记录。</p>}
      </div>
    </>
  )
}
