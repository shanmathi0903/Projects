package mainFolder;

import dbFolder.CustomerDb;
import dbFolder.BusOperatorDb;
import dbFolder.BusDb;
import beanFolder.Admin;
import dbFolder.OperatorsBusDb;
import dbFolder.BookingDb;

import java.util.Scanner;
import java.util.ArrayList;
import java.time.LocalDate;
import java.time.format.DateTimeFormatter;

public class Main{
	public static void main(String[] args){
		Scanner scanner=new Scanner(System.in);
	
		CustomerDb customer=new CustomerDb();
		BusOperatorDb busOperator=new BusOperatorDb();
		BusDb bus=new BusDb();
		OperatorsBusDb operatorsBus=new OperatorsBusDb();
		BookingDb booking=new BookingDb();
		Admin admin=new Admin();
		
		System.out.println("1. Login");
		System.out.println("2. Logout");
		System.out.print("Enter your Choice : ");
		int num=scanner.nextInt();
		
		while(num==1){
			System.out.println();
			System.out.println("1. Admin Login");
			System.out.println("2. BusOperator Login");
			System.out.println("3. Customer Login");
			System.out.println("4. Exit");
			System.out.print("Enter your Choice : ");
			int choice=scanner.nextInt();
			scanner.nextLine();
			System.out.println();
			
			if(choice==1){
				System.out.print("Enter Email : ");
				String email=scanner.nextLine();
				System.out.print("Enter Password : ");
				String password=scanner.nextLine();
				
				if(email.equals(admin.getEmail()) && password.equals(admin.getPassword())){
					while(choice==1){
						System.out.println();
						System.out.println("1. Add Bus Operator");
						System.out.println("2. Add Customer");
						System.out.println("3. View Bus Operators");
						System.out.println("4. View Buses");
						System.out.println("5. Search Bus throgh routes ");
						System.out.println("6. View Customers list through bus ");
						System.out.println("7. Logout");
						System.out.print("Enter your choice : ");
						int option=scanner.nextInt();
						scanner.nextLine();
						System.out.println();
						
						String userPassword="redbus";						
						if(option==1){
							System.out.print("Enter Name: ");
							String name=scanner.nextLine();
							System.out.print("Enter Phone Number: ");
							long number=scanner.nextLong();
							scanner.nextLine();
							System.out.print("Enter Email: ");
							String userEmail=scanner.next();
							
							busOperator.addUserDetails(name,number,userEmail,userPassword);
						}
						else if(option==2){
							System.out.print("Enter your Name: ");
							String name=scanner.nextLine();
							System.out.print("Enter your Phone Number: ");
							long number=scanner.nextLong();
							scanner.nextLine();
							System.out.print("Enter your Email: ");
							String userEmail=scanner.next();
							
							customer.addUserDetails(name,number,userEmail,userPassword);
						}
						else if(option==3){
							busOperator.displayBusOperators();
						}
						else if(option==4){
							bus.displayBusDetails();
						}
						else if(option==5){
							System.out.print("Enter Source : ");
							String source=scanner.nextLine();
							System.out.print("Enter Destination : ");
							String destination=scanner.nextLine();
							
							bus.searchBus(source,destination);
						}
						else if(option==6){
							System.out.print("Enter Bus Name : ");
							String busName=scanner.nextLine();
							System.out.print("Enter Bus Registration Number : ");
							String regNo=scanner.nextLine();
							System.out.print("Enter date (dd-mm-yyyy) : ");
							String date=scanner.nextLine();

							booking.displayBookingsForBus(busName,regNo,date);
						}
							
						else if(option==7){
							break;
						}
						
					}
				}
				else{
					System.out.println("Invalid email or password");
				}
			}
			else if(choice==2){
				System.out.print("Do you already have an account (yes/no): ");
				String input=scanner.next();
				scanner.nextLine();
				if(input.equals("no")){
					System.out.print("Enter your Name: ");
					String name=scanner.nextLine();
					System.out.print("Enter your Phone Number: ");
					long number=scanner.nextLong();
					scanner.nextLine();
					System.out.print("Enter your Email: ");
					String email=scanner.next();
					System.out.print("Enter your Password: ");
					String password=scanner.next();
					busOperator.addUserDetails(name,number,email,password);
				}
				else{
					System.out.print("Enter your Email: ");
					String email=scanner.next();
					System.out.print("Enter your Password: ");
					String password=scanner.next();
					
					if(busOperator.checkAccount(email,password)){
						int id=busOperator.getId(email,password);
						while(choice==2){	
							System.out.println();						
							System.out.println("1. Add Bus");
							System.out.println("2. View Bus");
							System.out.println("3. View Customer List through Bus");
							System.out.println("4. logout");
							System.out.print("Enter your choice: ");
							int option=scanner.nextInt();
							scanner.nextLine();
							System.out.println();
							
							if(option==1){
								System.out.print("Enter Bus Name: ");
								String busName=scanner.nextLine();
								System.out.print("Enter Registration Number: ");
								String regNo=scanner.nextLine();
								System.out.print("Enter Source: ");
								String source=scanner.nextLine();
								System.out.print("Enter Destination: ");
								String destination=scanner.nextLine();
								
								ArrayList<String> route=new ArrayList<>();
								System.out.print("Enter number of stops between source and destination: ");
								int noOfRoute=scanner.nextInt();
								scanner.nextLine();
								for(int i=0;i<noOfRoute;i++){
									System.out.print("Enter stop Name "+(i+1)+": ");
									String place=scanner.nextLine();
									route.add(place);
								}

								ArrayList<Float> price=new ArrayList<>();
								System.out.println("Enter price for each stop:");
								for(int i=0;i<=noOfRoute;i++){
									String from=(i==0)?source:route.get(i-1);
									String to=(i==noOfRoute)?destination:route.get(i);
									System.out.print("Price from "+from+" to "+to+": ");
									float cost=scanner.nextFloat();
									price.add(cost);
								}
								scanner.nextLine();

								System.out.print("Enter number of Seater seats: ");
								int seater=scanner.nextInt();
								System.out.print("Enter number of Sleeper seats: ");
								int sleeper=scanner.nextInt();
								System.out.print("Enter Sleeper seat price: ");
								float sleeperPrice=scanner.nextFloat();
								System.out.print("Does the bus have AC? (true/false): ");
								boolean hasAc=scanner.nextBoolean();
								scanner.nextLine();

								bus.addBusDetails(busName,regNo,source,destination,route,price,seater,sleeper,sleeperPrice,hasAc);

								operatorsBus.add(id,busName,regNo);
							}
							else if(option==2){
								bus.displayBusDetails();
							}
							else if(option==3){
								System.out.print("Enter Bus Name : ");
								String busName=scanner.nextLine();
								System.out.print("Enter Bus Registration Number : ");
								String regNo=scanner.nextLine();
								System.out.print("Enter date (dd-mm-yyyy) : ");
								String date=scanner.nextLine();

								booking.displayBookingsForBus(busName,regNo,date);
							}
							else if(option==4){
								break;
							}
						}
					}
					else{
						System.out.println("Invalid email or password");
					}
				}
			}
			else if(choice==3){
				System.out.print("Do you already have an account (yes/no): ");
				String input=scanner.next();
				scanner.nextLine();
				if(input.equals("no")){
					System.out.print("Enter your Name: ");
					String name=scanner.nextLine();
					System.out.print("Enter your Phone Number: ");
					long number=scanner.nextInt();
					scanner.nextLine();
					System.out.print("Enter your Email: ");
					String email=scanner.next();
					System.out.print("Enter your Password: ");
					String password=scanner.next();
					customer.addUserDetails(name,number,email,password);
				}
				else{
					System.out.print("Enter your Email: ");
					String email=scanner.next();
					System.out.print("Enter your Password: ");
					String password=scanner.next();
					
					if(customer.checkAccount(email,password)){
						String customerName=customer.findCustomerName(email,password);
						long phoneNumber=customer.findCustomerNumber(email,password);
						while(choice==3){
							System.out.println();
							System.out.println("1. View Bus");
							System.out.println("2. Search bus ");
							System.out.println("3. Show Available Tickets in Bus");
							System.out.println("4. Book Bus");
							System.out.println("5. Cancel Bus Ticket");
							System.out.println("6. View My History");
							System.out.println("7. Display future booking");
							System.out.println("8. Logout");
							System.out.print("Enter your choice: ");
							int option=scanner.nextInt();
							scanner.nextLine();
							System.out.println();
							
							if(option==1){
								bus.displayBusDetails();
							}
							else if(option==2){
								System.out.print("Enter Source : ");
								String source=scanner.nextLine();
								System.out.print("Enter Destination : ");
								String destination=scanner.nextLine();
								
								bus.searchBus(source,destination);
							}
							else if(option==3){
								System.out.print("Enter Bus Name : ");
								String busName=scanner.nextLine();
								System.out.print("Enter Bus Registration Number : ");
								String regNo=scanner.nextLine();
								System.out.print("Enter date to travel(dd-mm-yyyy) : ");
								String date=scanner.nextLine();
								System.out.println("Available seats in "+busName);
								bus.displaySeatAvailability(busName,regNo,date);
							}
							else if(option==4){
								System.out.print("Enter Bus Name : ");
								String busName=scanner.nextLine();
								System.out.print("Enter Bus Registration Number : ");
								String regNo=scanner.nextLine();
								System.out.print("Enter date to travel(dd-mm-yyyy) : ");
								String date=scanner.nextLine();
								System.out.println("Available seats in "+busName);
								bus.displaySeatAvailability(busName,regNo,date);
								scanner.nextLine();
								System.out.print("Enter Starting point : ");
								String source=scanner.nextLine();
								System.out.print("Enter Destination point : ");
								String destination=scanner.nextLine();
								System.out.print("Enter no.of.sitting seats : ");
								int si=scanner.nextInt();
								int[] siSeats=new int[si];
								if(si<=bus.getAvailableSeater(busName,regNo)){
									System.out.print("Enter seats to book :  ");
									
									for(int i=0;i<si;i++){
										siSeats[i]=scanner.nextInt();
									}
								}
								else{
									System.out.println("Requested seats are not available");
								}
								System.out.print("Enter no.of.sleeper seats : ");
								int sl=scanner.nextInt();
								int[] slSeats=new int[sl];
								
								if(sl<=bus.getAvailableSleeper(busName,regNo)){
									System.out.print("Enter seats to book :  ");
			
									for(int i=0;i<sl;i++){
										slSeats[i]=scanner.nextInt();
									}
								}
								else{
									System.out.println("Requested seats are not available");
								}
								if(bus.bookBus(busName,regNo,date,source,destination,siSeats,slSeats)){
									ArrayList<Integer> bookedSeater=new ArrayList<>();
									for(int s:siSeats){
										bookedSeater.add(s);
									}
									ArrayList<Integer> bookedSleeper=new ArrayList<>();
									for(int s:slSeats){
										bookedSleeper.add(s);
									}
									
									booking.addBooking(customerName,phoneNumber,busName,regNo,bookedSeater,bookedSleeper,source,destination,date);
								}
								else{
									System.out.println("Bus is not booked");
								}
							}
								
							else if(option==5){
								System.out.println("Enter bus details to cancel ticket : ");
								System.out.print("Enter Bus Name : ");
								String busName=scanner.nextLine();
								System.out.print("Enter Bus Registration Number : ");
								String regNo=scanner.nextLine();
								System.out.print("Enter travel date(dd-mm-yyyy) : ");
								String date=scanner.nextLine();
								
								if(booking.isAlreadyBooked(customerName,phoneNumber,busName,regNo,date)){
									LocalDate today=LocalDate.now();
									DateTimeFormatter formatter=DateTimeFormatter.ofPattern("dd-MM-yyyy");
									LocalDate dateObj=LocalDate.parse(date,formatter);
									if(dateObj.isAfter(today)){
										ArrayList<Integer> siSeats=booking.getBookedSiSeats(customerName,phoneNumber,busName,regNo,date);
										ArrayList<Integer> slSeats=booking.getBookedSlSeats(customerName,phoneNumber,busName,regNo,date);	
										bus.cancelBusTicket(busName,regNo,date,siSeats,slSeats);
										booking.cancelBooking(customerName,phoneNumber,regNo);
									}
									
								}
				
							}
							else if(option==6){
								LocalDate today=LocalDate.now();
								booking.showHistory(customerName,phoneNumber,today);
							}
							else if(option==7){
								LocalDate today=LocalDate.now();
								booking.futureBooking(customerName,phoneNumber,today);
							}
							else if(option==8){
								break;
							}
						}
					}
					else{
						System.out.println("Invalid email or password");
					}
				}
			}
			
			else if(choice==4){
				break;
			}
		}
		
	}
}
		
