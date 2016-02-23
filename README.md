# Laasti/Sessions

A nice simple session abstraction that works with PSR7 Http Message. 

## Installation

```
composer require laasti/sessions
```

## Usage

The package provides by default stores sessions to filesystem and persists sessions with cookies.

A middleware is responsible to insert the session in the request and to persist it in the response.

The middleware uses a HttpPersisterInterface which does all the background work to manipulate requests and responses.

**A word of caution the id of a session is immutable. Changing the id with withSessionId results in a new instance.

For that reason, it is recommended that only middlewares should mess with the session id and each middleware is responsible for the persistence of its new session.

To ease the process, you can easily reuse the persister across multiple middlewares in case you need to regenerate the session.

## Contributing

1. Fork it!
2. Create your feature branch: `git checkout -b my-new-feature`
3. Commit your changes: `git commit -am 'Add some feature'`
4. Push to the branch: `git push origin my-new-feature`
5. Submit a pull request :D

## History

See Github's releases, or tags

## Credits

Author: Sonia Marquette (@nebulousGirl)

## License

Released under the MIT License. See LICENSE.txt file.