import { useEffect, useState } from 'react'
import { api } from '../../api'

export default function AdminDatasets() {
  const [datasets, setDatasets] = useState([])
  const [error, setError] = useState('')
  const [ok, setOk] = useState('')

  // new dataset form
  const [name, setName] = useState('')
  const [code, setCode] = useState('')
  const [description, setDescription] = useState('')

  // new version form
  const [targetId, setTargetId] = useState('')
  const [version, setVersion] = useState('')
  const [changelog, setChangelog] = useState('')
  const [restricted, setRestricted] = useState('')
  const [file, setFile] = useState(null)

  function refresh() {
    api.get('/api/datasets').then(setDatasets).catch((e) => setError(e.message))
  }

  useEffect(refresh, [])

  async function createDataset(e) {
    e.preventDefault()
    setError(''); setOk('')
    try {
      await api.post('/api/admin/datasets', { name, code, description })
      setOk(`数据集「${name}」已创建`)
      setName(''); setCode(''); setDescription('')
      refresh()
    } catch (err) { setError(err.message) }
  }

  async function uploadVersion(e) {
    e.preventDefault()
    setError(''); setOk('')
    if (!file) { setError('请选择 CSV 文件'); return }
    const form = new FormData()
    form.append('version', version)
    form.append('changelog', changelog)
    form.append('file', file)
    const fields = restricted.split(',').map((s) => s.trim()).filter(Boolean)
    fields.forEach((f) => form.append('restricted_fields[]', f))
    try {
      const created = await api.postForm(`/api/admin/datasets/${targetId}/versions`, form)
      setOk(`版本 v${created.version} 已发布。已有授权仍锁定在原版本，不受影响。`)
      setVersion(''); setChangelog(''); setRestricted(''); setFile(null)
      refresh()
    } catch (err) { setError(err.message) }
  }

  return (
    <>
      <h1>数据集管理</h1>
      {error && <p className="error">{error}</p>}
      {ok && <p className="success">{ok}</p>}

      <div className="card">
        <h2>现有数据集</h2>
        <table>
          <thead><tr><th>ID</th><th>名称</th><th>标识</th><th>最新版本</th><th>公开字段数</th><th>受限字段数</th></tr></thead>
          <tbody>
            {datasets.map((d) => (
              <tr key={d.id}>
                <td>#{d.id}</td>
                <td>{d.name}</td>
                <td className="muted">{d.code}</td>
                <td>{d.latest_version ? `v${d.latest_version.version}` : '—'}</td>
                <td>{d.public_fields.length}</td>
                <td>{d.restricted_field_count}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <div className="card">
        <h2>新建数据集</h2>
        <form onSubmit={createDataset}>
          <div className="grid">
            <div>
              <label>名称</label>
              <input value={name} onChange={(e) => setName(e.target.value)} required />
            </div>
            <div>
              <label>标识（字母数字与连字符）</label>
              <input value={code} onChange={(e) => setCode(e.target.value)} pattern="[A-Za-z0-9_-]+" required />
            </div>
          </div>
          <label>描述</label>
          <textarea rows="2" value={description} onChange={(e) => setDescription(e.target.value)} />
          <div className="actions"><button className="primary">创建</button></div>
        </form>
      </div>

      <div className="card">
        <h2>发布新版本（数据集更新）</h2>
        <p className="muted">上传 CSV 作为新版本。更新只新增版本：已有授权仍锁定在原版本与原字段范围，不会自动扩大。</p>
        <form onSubmit={uploadVersion}>
          <div className="grid">
            <div>
              <label>目标数据集</label>
              <select value={targetId} onChange={(e) => setTargetId(e.target.value)} required>
                <option value="">请选择</option>
                {datasets.map((d) => <option key={d.id} value={d.id}>{d.name}</option>)}
              </select>
            </div>
            <div>
              <label>版本号</label>
              <input value={version} onChange={(e) => setVersion(e.target.value)} placeholder="如 2.0" required />
            </div>
          </div>
          <label>CSV 数据文件</label>
          <input type="file" accept=".csv" onChange={(e) => setFile(e.target.files[0])} required />
          <label>受限字段（CSV 列名，逗号分隔）</label>
          <input value={restricted} onChange={(e) => setRestricted(e.target.value)} placeholder="如 meter_id, room_number" />
          <label>版本说明</label>
          <input value={changelog} onChange={(e) => setChangelog(e.target.value)} />
          <div className="actions"><button className="primary">发布版本</button></div>
        </form>
      </div>
    </>
  )
}
