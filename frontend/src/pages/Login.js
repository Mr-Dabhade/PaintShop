import React, { useState } from 'react';
import axios from 'axios';
import '../styles/auth.css';

const Login = ({ onLogin }) => {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);
        setError('');
        try {
            const response = await axios.post('http://localhost:8000/api/auth/login', { email, password });
            if (response.data.success) {
                const userData = {
                    id: response.data.user.id,
                    email: response.data.user.email,
                    name: response.data.user.name,
                    token: response.data.token,
                };
                onLogin(userData);
            }
        } catch (err) {
            setError(err.response?.data?.message || 'Login failed. Please try again.');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="auth-container">
            <div className="auth-card">
                <h1>PaintShop</h1>
                <h2>Login</h2>
                <form onSubmit={handleSubmit}>
                    {error && <div className="alert alert-error">{error}</div>}
                    <div className="form-group">
                        <label>Email</label>
                        <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required placeholder="Enter your email" />
                    </div>
                    <div className="form-group">
                        <label>Password</label>
                        <input type="password" value={password} onChange={(e) => setPassword(e.target.value)} required placeholder="Enter your password" />
                    </div>
                    <button type="submit" className="btn btn-primary" disabled={loading}> {loading ? 'Logging in...' : 'Login'} </button>
                </form>
                <p className="auth-link">
                    Don't have an account? <a href="/register">Register here</a>
                </p>
            </div>
        </div>
    );
};

export default Login;