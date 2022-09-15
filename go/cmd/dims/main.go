/*
 * Genarate rsa keys.
 */

package main

import (
	"context"
	"database/sql"
	"fmt"
	"log"
	"os"

	_ "github.com/go-sql-driver/mysql"

	"nathejk.dk/nathejk/table"
	"nathejk.dk/pkg/closers"
	"nathejk.dk/pkg/memorystream"
	"nathejk.dk/pkg/nats"
	"nathejk.dk/pkg/sqlpersister"
	"nathejk.dk/pkg/stream"
	"nathejk.dk/pkg/streaminterface"
)

func main() {
	fmt.Println("Starting 'scan-app' ingest service")

	closer := closers.New().ExitOnSigInt()
	defer closer.Close()

	natsstream := nats.NewNATSStreamUnique(os.Getenv("STAN_DSN"), "scan-app-dims")
	closer.AddCloser(natsstream)

	//state := sqlstate.New(os.Getenv("MYSQL_DSN"))

	log.Printf("Connecting to database %q", os.Getenv("DB_DSN"))
	db, err := sql.Open("mysql", os.Getenv("DB_DSN"))
	if err != nil {
		log.Fatal(err)
	}
	sqlw := sqlpersister.New(db)

	memstream := memorystream.New()
	dstmux := stream.NewStreamMux(memstream)
	dstmux.Handles(natsstream, "nathejk") //d.stream.Channels()...)
	dstswtch, err := stream.NewSwitch(dstmux, []streaminterface.Consumer{
		table.NewPatrulje(sqlw),
		table.NewSpejder(sqlw),
		table.NewPersonnel(sqlw, memstream),
	})
	if err != nil {
		log.Fatal(err)
	}
	ctx := context.Background()
	live := make(chan struct{})
	go func() {
		err = dstswtch.Run(ctx, func() {
			//dstswtch.Close()
			//log.Printf("Closing")
			live <- struct{}{}
		})
		if err != nil {
			log.Fatal(err)
		}
	}()
	// Waiting for live
	select {
	case <-ctx.Done():
		log.Fatal(ctx.Err())
	case <-live:
	}
	log.Printf("Caught up dono, switching to live")
	select {}
}
