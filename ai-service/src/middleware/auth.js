export function requireServiceKey(req, res, next) {
  const expected = process.env.AI_SERVICE_KEY || 'banwohub-ai-dev-key'
  const header = req.headers.authorization || ''
  const token = header.startsWith('Bearer ') ? header.slice(7) : ''

  if (!token || token !== expected) {
    return res.status(401).json({ message: 'Unauthorized.' })
  }

  return next()
}
