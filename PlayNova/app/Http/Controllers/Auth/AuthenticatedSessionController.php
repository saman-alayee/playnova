public function store(Request $request)
{
    $request->validate([
        'login' => ['required', 'string'],
        'password' => ['required', 'string'],
    ]);

    $login = $request->input('login');
    $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

    $user = User::where($field, $login)->first();

    if ($user && password_verify($request->password, $user->password)) {
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        return redirect()->intended('/');
    }

    return back()->withErrors([
        'login' => 'اطلاعات وارد شده صحیح نیست.',
    ])->onlyInput('login');
}