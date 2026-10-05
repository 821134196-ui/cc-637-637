import { useEffect, useState } from 'react'
import { api } from '../api'

export default function MyCitations() {
  const [citations, setCitations] = useState([])
  const [grants, setGrants] = useState([])
  const [grantId, setGrantId] = useState('')
  const [title, setTitle] = useState('')
  const [venue, setVenue] = useState('')
  const [url, setUrl] = useState('')
  const [publishedAt, setPublishedAt] = useState('')
  const [error, setError] = useState('')
  const [ok, setOk] = useState('')

  function refresh() {
    api.get('/api/citations').then(setCitations).catch((e) => setError(e.message))
    api.get('/api/grants').then(setGrants).catch(() => {})
  }

  useEffect(refresh, [])

  async function submit(e) {
    e.preventDefault()
    setError('')
    setOk('')
    try {
      const created = await api.post('/api/citations', {
        access_grant_id: Number(grantId),
        title, venue: venue || undefined, url: url || undefined, published_at: publishedAt,
      })
      setOk(`登记成功，引用编号：${created.citation_number}`)
      setTitle(''); setVenue(''); setUrl(''); setPublishedAt('')
      refresh()
    } catch (err) {
      setError(err.message)
    }
  }

  return (
    <>
      <h1>成果引用登记</h1>
      <div className="card">
        <h2>登记新成果</h2>
        <p className="muted">发表成果后，请登记其使用的数据授权。引用将关联该授权实际使用的数据版本；授权到期或撤回后，已登记的引用仍然保留。</p>
        <form onSubmit={submit}>
          <label>使用的数据授权</label>
          <select value={grantId} onChange={(e) => setGrantId(e.target.value)} required>
            <option value="">请选择</option>
            {grants.map((g) => (
              <option key={g.id} value={g.id}>
                #{g.id} {g.dataset.name} v{g.version.version}（{g.status === 'active' ? '生效中' : g.status === 'expired' ? '已到期' : '已撤回'}）
              </option>
            ))}
          </select>
          <label>成果标题</label>
          <input value={title} onChange={(e) => setTitle(e.target.value)} required />
          <div className="grid">
            <div>
              <label>发表载体（期刊/会议）</label>
              <input value={venue} onChange={(e) => setVenue(e.target.value)} />
            </div>
            <div>
              <label>发表日期</label>
              <input type="date" value={publishedAt} onChange={(e) => setPublishedAt(e.target.value)} required />
            </div>
          </div>
          <label>成果链接（可选）</label>
          <input type="url" value={url} onChange={(e) => setUrl(e.target.value)} placeholder="https://" />
          {error && <p className="error">{error}</p>}
          {ok && <p className="success">{ok}</p>}
          <div className="actions">
            <button className="primary" disabled={!grantId}>登记</button>
          </div>
        </form>
      </div>

      <h2>已登记成果</h2>
      {citations.length === 0 && <p className="muted">暂无登记记录。</p>}
      {citations.map((c) => (
        <div className="card" key={c.id}>
          <h3>{c.citation_number}：{c.title}</h3>
          <p className="muted">
            数据：{c.dataset.name} v{c.version.version} · 发表于 {c.published_at}
            {c.venue && ` · ${c.venue}`}
            {c.url && <> · <a href={c.url} target="_blank" rel="noreferrer">链接</a></>}
          </p>
        </div>
      ))}
    </>
  )
}
