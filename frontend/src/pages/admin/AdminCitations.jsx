import { useEffect, useState } from 'react'
import { api } from '../../api'

export default function AdminCitations() {
  const [citations, setCitations] = useState([])
  const [error, setError] = useState('')

  useEffect(() => {
    api.get('/api/admin/citations').then(setCitations).catch((e) => setError(e.message))
  }, [])

  return (
    <>
      <h1>成果引用记录</h1>
      {error && <p className="error">{error}</p>}
      <div className="card">
        <table>
          <thead>
            <tr><th>引用编号</th><th>成果</th><th>登记人</th><th>数据集 / 版本</th><th>发表日期</th><th>授权状态</th></tr>
          </thead>
          <tbody>
            {citations.map((c) => (
              <tr key={c.id}>
                <td>{c.citation_number}</td>
                <td>{c.title}{c.venue && <span className="muted">（{c.venue}）</span>}</td>
                <td>{c.user.name}</td>
                <td>{c.dataset.name} v{c.version.version}</td>
                <td className="muted">{c.published_at}</td>
                <td>
                  <span className={`badge ${c.grant_status}`}>
                    {c.grant_status === 'active' ? '生效中' : c.grant_status === 'expired' ? '已到期' : '已撤回'}
                  </span>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        {citations.length === 0 && <p className="muted">暂无引用记录。</p>}
      </div>
    </>
  )
}
